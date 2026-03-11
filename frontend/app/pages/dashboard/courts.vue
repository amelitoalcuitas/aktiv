<script setup lang="ts">
import { z } from 'zod';
import { useHubs } from '~/composables/useHubs';
import { useHubStore } from '~/stores/hub';
import type { Court, Hub } from '~/types/hub';

definePageMeta({ layout: 'dashboard', middleware: 'auth' });

const hubStore = useHubStore();
const { fetchCourts, createCourt, updateCourt, deleteCourt } = useHubs();
const toast = useToast();
const route = useRoute();

// ── Hub selector ──────────────────────────────────────────────────────────────

const selectedHubId = ref<number | undefined>(undefined);
const courts = ref<Court[]>([]);
const courtsLoading = ref(false);

const hubOptions = computed(() =>
  hubStore.myHubs.map((h: Hub) => ({ label: h.name, value: h.id }))
);
const selectedHub = computed<Hub | undefined>(() =>
  hubStore.myHubs.find((h: Hub) => h.id === selectedHubId.value)
);

onMounted(async () => {
  await hubStore.fetchMyHubs();
  if (hubStore.myHubs.length) {
    const queryHubIdRaw = Array.isArray(route.query.hubId)
      ? route.query.hubId[0]
      : route.query.hubId;
    const queryHubId = Number(queryHubIdRaw);
    const hasMatchingHub = hubStore.myHubs.some(
      (h: Hub) => h.id === queryHubId
    );

    selectedHubId.value = hasMatchingHub ? queryHubId : hubStore.myHubs[0]?.id;
    await loadCourts();
  }
});

watch(selectedHubId, async () => {
  courts.value = [];
  if (selectedHubId.value) await loadCourts();
});

async function loadCourts() {
  if (selectedHubId.value === undefined) return;
  courtsLoading.value = true;
  try {
    courts.value = await fetchCourts(selectedHubId.value);
  } finally {
    courtsLoading.value = false;
  }
}

// ── Add / Edit court ──────────────────────────────────────────────────────────

const isFormOpen = ref(false);
const editingCourt = ref<Court | null>(null);
const formLoading = ref(false);

const SPORT_OPTIONS = [
  { label: 'Pickleball', value: 'pickleball' },
  { label: 'Badminton', value: 'badminton' },
  { label: 'Tennis', value: 'tennis' },
  { label: 'Basketball', value: 'basketball' },
  { label: 'Volleyball', value: 'volleyball' }
];

const SURFACE_OPTIONS = [
  { label: 'Concrete', value: 'concrete' },
  { label: 'Hardcourt', value: 'hardcourt' },
  { label: 'Clay', value: 'clay' },
  { label: 'Synthetic', value: 'synthetic' },
  { label: 'Grass', value: 'grass' },
  { label: 'Wood', value: 'wood' }
];

const courtForm = reactive({
  name: '',
  surface: 'concrete' as string,
  indoor: true,
  price_per_hour: '',
  max_players: '',
  is_active: true,
  sports: [] as string[]
});

watch(
  () => courtForm.is_active,
  (isActive) => {
    if (!isActive) clearConditionalFormErrors();
  }
);

const formSubmitted = ref(false);

const formErrors = reactive({
  name: '',
  surface: '',
  price_per_hour: '',
  max_players: '',
  sports: ''
});

function clearFormErrors() {
  formErrors.name = '';
  formErrors.surface = '';
  formErrors.price_per_hour = '';
  formErrors.max_players = '';
  formErrors.sports = '';
}

function clearConditionalFormErrors() {
  formErrors.surface = '';
  formErrors.price_per_hour = '';
  formErrors.max_players = '';
  formErrors.sports = '';
}

function normalizeTextValue(value: string | number | null | undefined) {
  if (value === null || value === undefined) return '';
  return String(value).trim();
}

function normalizeUnknownText(value: unknown) {
  if (
    typeof value === 'string' ||
    typeof value === 'number' ||
    value === null ||
    value === undefined
  ) {
    return normalizeTextValue(value);
  }

  return '';
}

const courtFormSchema = z
  .object({
    name: z.preprocess(
      (value) => normalizeUnknownText(value),
      z.string().min(1, 'Court name is required.')
    ),
    surface: z.preprocess((value) => normalizeUnknownText(value), z.string()),
    price_per_hour: z.preprocess(
      (value) => normalizeUnknownText(value),
      z.string()
    ),
    max_players: z.preprocess(
      (value) => normalizeUnknownText(value),
      z.string()
    ),
    sports: z.array(z.string()),
    is_active: z.boolean()
  })
  .superRefine((data, ctx) => {
    if (!data.is_active) return;

    if (!data.surface) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['surface'],
        message: 'Surface is required when court is Active.'
      });
    }

    if (!data.price_per_hour) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['price_per_hour'],
        message: 'Price per hour is required when court is Active.'
      });
    } else if (Number.isNaN(parseFloat(data.price_per_hour))) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['price_per_hour'],
        message: 'Price per hour must be a valid number.'
      });
    }

    if (!data.max_players) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['max_players'],
        message: 'Max players is required when court is Active.'
      });
    } else {
      const parsedMaxPlayers = parseInt(data.max_players, 10);
      if (Number.isNaN(parsedMaxPlayers) || parsedMaxPlayers < 1) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          path: ['max_players'],
          message: 'Max players must be at least 1.'
        });
      }
    }

    if (!data.sports.length) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['sports'],
        message: 'Select at least one sport when court is Active.'
      });
    }
  });

function setFormErrorsFromZod(error: z.ZodError) {
  clearFormErrors();
  for (const issue of error.issues) {
    const key = issue.path[0];
    if (typeof key !== 'string') continue;

    if (key === 'name' && !formErrors.name) formErrors.name = issue.message;
    if (key === 'surface' && !formErrors.surface) {
      formErrors.surface = issue.message;
    }
    if (key === 'price_per_hour' && !formErrors.price_per_hour) {
      formErrors.price_per_hour = issue.message;
    }
    if (key === 'max_players' && !formErrors.max_players) {
      formErrors.max_players = issue.message;
    }
    if (key === 'sports' && !formErrors.sports)
      formErrors.sports = issue.message;
  }
}

function validateCourtForm() {
  const parsed = courtFormSchema.safeParse({
    name: courtForm.name,
    surface: courtForm.surface,
    price_per_hour: courtForm.price_per_hour,
    max_players: courtForm.max_players,
    sports: courtForm.sports,
    is_active: courtForm.is_active
  });

  if (parsed.success) {
    clearFormErrors();
    return true;
  }

  setFormErrorsFromZod(parsed.error);
  return false;
}

function openAdd() {
  editingCourt.value = null;
  formSubmitted.value = false;
  clearFormErrors();
  Object.assign(courtForm, {
    name: '',
    surface: 'concrete',
    indoor: true,
    price_per_hour: '',
    max_players: '',
    is_active: true,
    sports: []
  });
  isFormOpen.value = true;
}

function openEdit(court: Court) {
  editingCourt.value = court;
  formSubmitted.value = false;
  clearFormErrors();
  Object.assign(courtForm, {
    name: court.name,
    surface: court.surface ?? '',
    indoor: court.indoor,
    price_per_hour: court.price_per_hour
      ? String(parseFloat(court.price_per_hour))
      : '',
    max_players: court.max_players != null ? String(court.max_players) : '',
    is_active: court.is_active,
    sports: [...court.sports]
  });
  isFormOpen.value = true;
}

async function submitForm() {
  if (selectedHubId.value === undefined) return;

  formSubmitted.value = true;

  if (!validateCourtForm()) {
    toast.add({
      title: 'Please fix the form errors before saving',
      color: 'error'
    });
    return;
  }

  formLoading.value = true;
  try {
    const pricePerHour = normalizeTextValue(courtForm.price_per_hour);
    const maxPlayers = normalizeTextValue(courtForm.max_players);

    const payload = {
      name: courtForm.name,
      surface: courtForm.surface || undefined,
      indoor: courtForm.indoor,
      price_per_hour: pricePerHour ? parseFloat(pricePerHour) : 0,
      max_players: maxPlayers ? parseInt(maxPlayers, 10) : null,
      is_active: courtForm.is_active,
      sports: courtForm.sports
    };

    if (editingCourt.value) {
      await updateCourt(selectedHubId.value, editingCourt.value.id, payload);
      toast.add({ title: 'Court updated', color: 'success' });
    } else {
      await createCourt(selectedHubId.value, payload);
      toast.add({ title: 'Court created', color: 'success' });
    }
    isFormOpen.value = false;
    await loadCourts();
    await hubStore.fetchMyHubs(); // refresh hub sports
  } catch {
    toast.add({ title: 'Failed to save court', color: 'error' });
  } finally {
    formLoading.value = false;
  }
}

// ── Delete court ──────────────────────────────────────────────────────────────

const isDeleteOpen = ref(false);
const deletingCourt = ref<Court | null>(null);
const deleteLoading = ref(false);

function openDelete(court: Court) {
  deletingCourt.value = court;
  isDeleteOpen.value = true;
}

async function confirmDelete() {
  if (selectedHubId.value === undefined || !deletingCourt.value) return;
  deleteLoading.value = true;
  try {
    await deleteCourt(selectedHubId.value, deletingCourt.value.id);
    toast.add({ title: 'Court deleted', color: 'success' });
    isDeleteOpen.value = false;
    await loadCourts();
    await hubStore.fetchMyHubs();
  } catch {
    toast.add({ title: 'Failed to delete court', color: 'error' });
  } finally {
    deleteLoading.value = false;
  }
}

// ── Helpers ────────────────────────────────────────────────────────────────────

function sportLabel(sport: string) {
  return sport.charAt(0).toUpperCase() + sport.slice(1);
}

function formatPrice(price: string) {
  return `₱${parseFloat(price).toFixed(0)}/hr`;
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-[#0f1728]">Courts</h1>
        <p class="mt-1 text-sm text-[#64748b]">Manage courts for your hubs.</p>
      </div>
      <UButton
        v-if="selectedHubId"
        icon="i-heroicons-plus"
        class="bg-[#004e89] font-semibold hover:bg-[#003d6b]"
        @click="openAdd"
      >
        Add Court
      </UButton>
    </div>

    <!-- Hubs loading -->
    <div
      v-if="!hubStore.initialized || hubStore.loading"
      class="flex items-center gap-2 text-[#64748b]"
    >
      <UIcon name="i-heroicons-arrow-path" class="h-5 w-5 animate-spin" />
      <span class="text-sm">Loading courts...</span>
    </div>

    <!-- No hubs state -->
    <div
      v-else-if="!hubStore.myHubs.length"
      class="rounded-2xl border border-dashed border-[#dbe4ef] bg-white p-12 text-center"
    >
      <UIcon
        name="i-heroicons-building-office-2"
        class="mx-auto h-12 w-12 text-[#c8d5e0]"
      />
      <h3 class="mt-4 text-base font-semibold text-[#0f1728]">No hubs yet</h3>
      <p class="mt-1 text-sm text-[#64748b]">
        Create a hub first before adding courts.
      </p>
      <UButton
        to="/hubs/create"
        icon="i-heroicons-plus"
        class="mt-5 bg-[#004e89] hover:bg-[#003d6b]"
      >
        Create Hub
      </UButton>
    </div>

    <template v-else>
      <!-- Hub selector -->
      <div class="mb-6 flex items-center gap-3">
        <label class="text-sm font-medium text-[#0f1728]">Hub:</label>
        <USelect v-model="selectedHubId" :items="hubOptions" class="w-64" />
      </div>

      <!-- Courts loading -->
      <div v-if="courtsLoading" class="flex items-center gap-2 text-[#64748b]">
        <UIcon name="i-heroicons-arrow-path" class="h-5 w-5 animate-spin" />
        <span class="text-sm">Loading courts…</span>
      </div>

      <!-- Courts empty -->
      <div
        v-else-if="!courts.length"
        class="rounded-2xl border border-dashed border-[#dbe4ef] bg-white p-10 text-center"
      >
        <UIcon
          name="i-heroicons-squares-2x2"
          class="mx-auto h-10 w-10 text-[#c8d5e0]"
        />
        <h3 class="mt-3 text-sm font-semibold text-[#0f1728]">No courts yet</h3>
        <p class="mt-1 text-xs text-[#64748b]">
          Add your first court to start accepting bookings.
        </p>
        <UButton
          icon="i-heroicons-plus"
          class="mt-4 bg-[#004e89] hover:bg-[#003d6b]"
          size="sm"
          @click="openAdd"
        >
          Add Court
        </UButton>
      </div>

      <!-- Courts table/list -->
      <div
        v-else
        class="overflow-hidden rounded-2xl border border-[#dbe4ef] bg-white"
      >
        <table class="w-full text-sm">
          <thead class="border-b border-[#dbe4ef] bg-[#f8fafc] text-[#64748b]">
            <tr>
              <th class="px-4 py-3 text-left font-medium">Name</th>
              <th class="hidden px-4 py-3 text-left font-medium sm:table-cell">
                Surface
              </th>
              <th class="hidden px-4 py-3 text-left font-medium md:table-cell">
                Indoor
              </th>
              <th class="px-4 py-3 text-left font-medium">Price/hr</th>
              <th class="hidden px-4 py-3 text-left font-medium lg:table-cell">
                Sports
              </th>
              <th class="px-4 py-3 text-left font-medium">Status</th>
              <th class="px-4 py-3 text-right font-medium">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#f0f4f8]">
            <tr
              v-for="court in courts"
              :key="court.id"
              class="hover:bg-[#fafcff]"
            >
              <td class="px-4 py-3 font-medium text-[#0f1728]">
                {{ court.name }}
              </td>
              <td
                class="hidden px-4 py-3 capitalize text-[#64748b] sm:table-cell"
              >
                {{ court.surface ?? '—' }}
              </td>
              <td class="hidden px-4 py-3 md:table-cell">
                <span
                  class="rounded-full px-2 py-0.5 text-xs font-medium"
                  :class="
                    court.indoor
                      ? 'bg-[#e8f0f8] text-[#004e89]'
                      : 'bg-[#f0f4f8] text-[#64748b]'
                  "
                >
                  {{ court.indoor ? 'Indoor' : 'Outdoor' }}
                </span>
              </td>
              <td class="px-4 py-3 text-[#0f1728]">
                {{ formatPrice(court.price_per_hour) }}
              </td>
              <td class="hidden px-4 py-3 lg:table-cell">
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="sport in court.sports"
                    :key="sport"
                    class="rounded-full bg-[#e8f0f8] px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-[#004e89]"
                  >
                    {{ sportLabel(sport) }}
                  </span>
                  <span
                    v-if="!court.sports.length"
                    class="text-xs text-[#64748b]"
                    >—</span
                  >
                </div>
              </td>
              <td class="px-4 py-3">
                <span
                  class="rounded-full px-2 py-0.5 text-xs font-medium"
                  :class="
                    court.is_active
                      ? 'bg-[#daf7d0] text-[#1e6a0f]'
                      : 'bg-[#fee2e2] text-[#9f1239]'
                  "
                >
                  {{ court.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-1">
                  <UButton
                    icon="i-heroicons-pencil-square"
                    color="neutral"
                    variant="ghost"
                    size="sm"
                    @click="openEdit(court)"
                  />
                  <UButton
                    icon="i-heroicons-trash"
                    color="error"
                    variant="ghost"
                    size="sm"
                    @click="openDelete(court)"
                  />
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- Court Form Modal -->
    <UModal
      v-model:open="isFormOpen"
      :title="editingCourt ? 'Edit Court' : 'Add Court'"
      :ui="{ content: 'max-w-lg' }"
    >
      <template #body>
        <form class="space-y-4" @submit.prevent="submitForm">
          <UFormField
            label="Court Name"
            required
            :error="
              formSubmitted && formErrors.name ? formErrors.name : undefined
            "
          >
            <UInput
              v-model="courtForm.name"
              placeholder="e.g. Court A"
              class="w-full"
            />
          </UFormField>

          <div class="grid grid-cols-2 gap-4">
            <UFormField
              label="Surface"
              :required="courtForm.is_active"
              :error="
                formSubmitted && formErrors.surface
                  ? formErrors.surface
                  : undefined
              "
            >
              <USelect
                v-model="courtForm.surface"
                :items="[SURFACE_OPTIONS]"
                class="w-full"
              />
            </UFormField>
            <UFormField
              label="Price per Hour (₱)"
              :required="courtForm.is_active"
              :error="
                formSubmitted && formErrors.price_per_hour
                  ? formErrors.price_per_hour
                  : undefined
              "
            >
              <UInput
                v-model="courtForm.price_per_hour"
                type="number"
                min="0"
                step="0.01"
                placeholder="500"
                class="w-full"
              />
            </UFormField>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <UFormField
              label="Max Players"
              :required="courtForm.is_active"
              :error="
                formSubmitted && formErrors.max_players
                  ? formErrors.max_players
                  : undefined
              "
            >
              <UInput
                v-model="courtForm.max_players"
                type="number"
                min="1"
                placeholder="4"
                class="w-full"
              />
            </UFormField>
          </div>

          <div class="flex items-center gap-6">
            <label class="flex cursor-pointer items-center gap-2 text-sm">
              <USwitch v-model="courtForm.indoor" />
              <span class="font-medium text-[#0f1728]">Indoor</span>
            </label>
            <label class="flex cursor-pointer items-center gap-2 text-sm">
              <USwitch v-model="courtForm.is_active" />
              <span class="font-medium text-[#0f1728]">Active</span>
            </label>
          </div>

          <p class="text-xs text-[#64748b]">
            Note: When <strong>Active</strong> is enabled, all fields are
            required before saving.
          </p>

          <UFormField label="Sports" :required="courtForm.is_active">
            <div class="flex flex-wrap gap-2 pt-1">
              <label
                v-for="opt in SPORT_OPTIONS"
                :key="opt.value"
                class="flex cursor-pointer items-center gap-1.5 rounded-full border px-3 py-1 text-sm font-medium transition"
                :class="
                  courtForm.sports.includes(opt.value)
                    ? 'border-[#004e89] bg-[#e8f0f8] text-[#004e89]'
                    : 'border-[#dbe4ef] text-[#64748b] hover:border-[#004e89]'
                "
              >
                <input
                  type="checkbox"
                  class="sr-only"
                  :value="opt.value"
                  :checked="courtForm.sports.includes(opt.value)"
                  @change="
                    courtForm.sports.includes(opt.value)
                      ? (courtForm.sports = courtForm.sports.filter(
                          (s) => s !== opt.value
                        ))
                      : courtForm.sports.push(opt.value)
                  "
                />
                {{ opt.label }}
              </label>
            </div>
            <p
              v-if="formSubmitted && formErrors.sports"
              class="mt-1 text-xs text-[#dc2626]"
            >
              {{ formErrors.sports }}
            </p>
          </UFormField>

          <div class="flex justify-end gap-2 pt-2">
            <UButton color="neutral" variant="ghost" @click="isFormOpen = false"
              >Cancel</UButton
            >
            <UButton type="submit" :loading="formLoading">
              {{ editingCourt ? 'Save Changes' : 'Add Court' }}
            </UButton>
          </div>
        </form>
      </template>
    </UModal>

    <!-- Delete Confirm Modal -->
    <UModal
      v-model:open="isDeleteOpen"
      title="Delete Court"
      :ui="{ content: 'max-w-sm' }"
    >
      <template #body>
        <p class="text-sm text-[#0f1728]">
          Are you sure you want to delete
          <strong>{{ deletingCourt?.name }}</strong
          >? This cannot be undone.
        </p>
        <div class="mt-5 flex justify-end gap-2">
          <UButton color="neutral" variant="ghost" @click="isDeleteOpen = false"
            >Cancel</UButton
          >
          <UButton color="error" :loading="deleteLoading" @click="confirmDelete"
            >Delete</UButton
          >
        </div>
      </template>
    </UModal>
  </div>
</template>
