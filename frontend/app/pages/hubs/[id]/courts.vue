<script setup lang="ts">
import { z } from 'zod';
import { useHubs } from '~/composables/useHubs';
import type { Court } from '~/types/hub';

definePageMeta({ middleware: 'auth', layout: 'dashboard-hub' });

const route = useRoute();
const { fetchCourts, createCourt, updateCourt, deleteCourt } = useHubs();
const toast = useToast();

const hubId = computed(() => String(route.params.id));

const manageTabs = computed(() => [
  {
    label: 'Hub',
    icon: 'i-heroicons-building-storefront',
    to: `/hubs/${hubId.value}/edit`
  },
  {
    label: 'Courts',
    icon: 'i-heroicons-squares-2x2',
    to: `/hubs/${hubId.value}/courts`
  },
  {
    label: 'Bookings',
    icon: 'i-heroicons-calendar-days',
    to: `/hubs/${hubId.value}/bookings`
  },
  {
    label: 'Events',
    icon: 'i-heroicons-megaphone',
    to: `/hubs/${hubId.value}/events`
  },
  {
    label: 'Reviews',
    icon: 'i-heroicons-star',
    to: `/hubs/${hubId.value}/reviews`
  },
  {
    label: 'Settings',
    icon: 'i-heroicons-cog-6-tooth',
    to: `/hubs/${hubId.value}/settings`
  }
]);

const courts = ref<Court[]>([]);
const courtsLoading = ref(false);

onMounted(async () => {
  await loadCourts();
});

async function loadCourts() {
  courtsLoading.value = true;
  try {
    courts.value = await fetchCourts(hubId.value);
  } finally {
    courtsLoading.value = false;
  }
}

// ── Add / Edit court ──────────────────────────────────────────────────────────

const isFormOpen = ref(false);
const editingCourt = ref<Court | null>(null);
const formLoading = ref(false);
const courtFormEl = useTemplateRef<HTMLFormElement>('courtFormEl');
const courtImageFile = ref<File | null>(null);
const courtImagePreview = ref<string | null>(null);
const removeCourtImage = ref(false);

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
  { label: 'Wood', value: 'wood' },
  { label: 'Other', value: 'other' }
];

const KNOWN_SURFACE_VALUES = SURFACE_OPTIONS.filter(
  (o) => o.value !== 'other'
).map((o) => o.value);

const courtForm = reactive({
  name: '',
  surface: 'concrete' as string,
  surface_custom: '',
  indoor: true,
  price_per_hour: '',
  is_active: true,
  sports: [] as string[]
});

const customSportInput = ref('');

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
  surface_custom: '',
  price_per_hour: '',
  sports: ''
});

function clearFormErrors() {
  formErrors.name = '';
  formErrors.surface = '';
  formErrors.surface_custom = '';
  formErrors.price_per_hour = '';
  formErrors.sports = '';
}

function clearConditionalFormErrors() {
  formErrors.surface = '';
  formErrors.surface_custom = '';
  formErrors.price_per_hour = '';
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
    surface_custom: z.preprocess(
      (value) => normalizeUnknownText(value),
      z.string().max(16, 'Surface name must be 16 characters or less.')
    ),
    price_per_hour: z.preprocess(
      (value) => normalizeUnknownText(value),
      z.string()
    ),
    sports: z.array(z.string()),
    is_active: z.boolean()
  })
  .superRefine((data, ctx) => {
    if (data.surface === 'other' && !data.surface_custom) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['surface_custom'],
        message: 'Please specify the surface type.'
      });
    }

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
    if (key === 'surface_custom' && !formErrors.surface_custom) {
      formErrors.surface_custom = issue.message;
    }
    if (key === 'price_per_hour' && !formErrors.price_per_hour) {
      formErrors.price_per_hour = issue.message;
    }
    if (key === 'sports' && !formErrors.sports)
      formErrors.sports = issue.message;
  }
}

function validateCourtForm() {
  const parsed = courtFormSchema.safeParse({
    name: courtForm.name,
    surface: courtForm.surface,
    surface_custom: courtForm.surface_custom,
    price_per_hour: courtForm.price_per_hour,
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
  courtImageFile.value = null;
  courtImagePreview.value = null;
  removeCourtImage.value = false;
  customSportInput.value = '';
  Object.assign(courtForm, {
    name: '',
    surface: 'concrete',
    surface_custom: '',
    indoor: true,
    price_per_hour: '',
    is_active: true,
    sports: []
  });
  isFormOpen.value = true;
}

function openEdit(court: Court) {
  editingCourt.value = court;
  formSubmitted.value = false;
  clearFormErrors();
  courtImageFile.value = null;
  courtImagePreview.value = court.image_url ?? null;
  removeCourtImage.value = false;
  customSportInput.value = '';
  const isKnownSurface = court.surface
    ? KNOWN_SURFACE_VALUES.includes(court.surface)
    : true;
  Object.assign(courtForm, {
    name: court.name,
    surface: court.surface ? (isKnownSurface ? court.surface : 'other') : '',
    surface_custom: court.surface && !isKnownSurface ? court.surface : '',
    indoor: court.indoor,
    price_per_hour: court.price_per_hour
      ? String(parseFloat(court.price_per_hour))
      : '',
    is_active: court.is_active,
    sports: [...court.sports]
  });
  isFormOpen.value = true;
}

async function submitForm() {
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

    const resolvedSurface =
      courtForm.surface === 'other'
        ? courtForm.surface_custom.trim() || undefined
        : courtForm.surface || undefined;

    const payload = {
      name: courtForm.name,
      surface: resolvedSurface,
      indoor: courtForm.indoor,
      price_per_hour: pricePerHour ? parseFloat(pricePerHour) : 0,
      is_active: courtForm.is_active,
      sports: courtForm.sports,
      court_image: courtImageFile.value ?? undefined
    };

    if (editingCourt.value) {
      await updateCourt(hubId.value, editingCourt.value.id, {
        ...payload,
        remove_court_image: removeCourtImage.value || undefined
      });
      toast.add({ title: 'Court updated', color: 'success' });
    } else {
      await createCourt(hubId.value, payload);
      toast.add({ title: 'Court created', color: 'success' });
    }
    isFormOpen.value = false;
    await loadCourts();
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
  if (!deletingCourt.value) return;
  deleteLoading.value = true;
  try {
    await deleteCourt(hubId.value, deletingCourt.value.id);
    toast.add({ title: 'Court deleted', color: 'success' });
    isDeleteOpen.value = false;
    await loadCourts();
  } catch {
    toast.add({ title: 'Failed to delete court', color: 'error' });
  } finally {
    deleteLoading.value = false;
  }
}

// ── Toggle active ─────────────────────────────────────────────────────────────

const togglingCourtId = ref<string | null>(null);

async function toggleActive(court: Court) {
  togglingCourtId.value = court.id;
  try {
    await updateCourt(hubId.value, court.id, {
      is_active: !court.is_active
    });
    court.is_active = !court.is_active;
  } catch {
    toast.add({ title: 'Failed to update status', color: 'error' });
  } finally {
    togglingCourtId.value = null;
  }
}

// ── Custom sport ──────────────────────────────────────────────────────────────

function addCustomSport() {
  const value = customSportInput.value.trim();
  if (!value || value.length > 32) return;
  if (courtForm.sports.some((s) => s.toLowerCase() === value.toLowerCase()))
    return;
  courtForm.sports.push(value);
  customSportInput.value = '';
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
    <HubTabNav :tabs="manageTabs" />

    <div class="mx-auto w-full max-w-[1400px] px-4 py-8 md:px-6">
      <!-- Header -->
      <div class="mb-6 flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-[#0f1728]">Courts</h1>
          <p class="mt-1 text-sm text-[#64748b]">Manage courts for this hub.</p>
        </div>
        <UButton
          icon="i-heroicons-plus"
          class="bg-[#004e89] font-semibold hover:bg-[#003d6b]"
          @click="openAdd"
        >
          Add Court
        </UButton>
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
              <td
                class="max-w-[200px] truncate px-4 py-3 font-medium text-[#0f1728]"
                :title="court.name"
              >
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
                <div class="flex items-center gap-2">
                  <USwitch
                    :model-value="court.is_active"
                    :disabled="togglingCourtId === court.id"
                    @update:model-value="toggleActive(court)"
                  />
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
                </div>
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
    </div>

    <!-- Court Form Modal -->
    <AppModal
      v-model:open="isFormOpen"
      :title="editingCourt ? 'Edit Court' : 'Add Court'"
      :ui="{ content: 'max-w-lg' }"
      :confirm="editingCourt ? 'Save Changes' : 'Add Court'"
      :confirm-loading="formLoading"
      @confirm="courtFormEl?.requestSubmit()"
    >
      <template #body>
        <form ref="courtFormEl" class="space-y-4" @submit.prevent="submitForm">
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
              maxlength="36"
            />
            <p class="mt-1 text-xs text-[var(--aktiv-muted)]">
              Max 36 characters
            </p>
          </UFormField>

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
            <template v-if="courtForm.surface === 'other'">
              <UInput
                v-model="courtForm.surface_custom"
                placeholder="Specify surface"
                class="mt-2 w-full"
                maxlength="16"
              />
              <p
                v-if="formSubmitted && formErrors.surface_custom"
                class="mt-1 text-xs text-[#dc2626]"
              >
                {{ formErrors.surface_custom }}
              </p>
              <p class="mt-1 text-xs text-[var(--aktiv-muted)]">
                Max 16 characters
              </p>
            </template>
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

          <AppImageUploader
            v-model="courtImageFile"
            :preview-url="courtImagePreview"
            label="Court Image (optional)"
            hint="Add a photo of the court. JPG, PNG or WebP, max 10 MB."
            @clear="
              removeCourtImage = true;
              courtImagePreview = null;
            "
          />

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
              <!-- Custom sports added by the user -->
              <label
                v-for="custom in courtForm.sports.filter(
                  (s) => !SPORT_OPTIONS.some((o) => o.value === s)
                )"
                :key="custom"
                class="flex cursor-pointer items-center gap-1.5 rounded-full border border-[#004e89] bg-[#e8f0f8] px-3 py-1 text-sm font-medium text-[#004e89] transition"
                @click="
                  courtForm.sports = courtForm.sports.filter(
                    (s) => s !== custom
                  )
                "
              >
                {{ sportLabel(custom) }}
                <UIcon name="i-heroicons-x-mark" class="h-3.5 w-3.5" />
              </label>
            </div>
            <!-- Add custom sport -->
            <div class="mt-2 flex gap-2">
              <UInput
                v-model="customSportInput"
                placeholder="Add a sport…"
                class="flex-1"
                maxlength="32"
                @keydown.enter.prevent="addCustomSport"
              />
              <UButton
                type="button"
                color="neutral"
                variant="outline"
                @click="addCustomSport"
              >
                Add
              </UButton>
            </div>
            <p
              v-if="formSubmitted && formErrors.sports"
              class="mt-1 text-xs text-[#dc2626]"
            >
              {{ formErrors.sports }}
            </p>
          </UFormField>
        </form>
      </template>
    </AppModal>

    <!-- Delete Confirm Modal -->
    <AppModal
      v-model:open="isDeleteOpen"
      title="Delete Court"
      :ui="{ content: 'max-w-sm' }"
      confirm="Delete"
      confirm-color="error"
      :confirm-loading="deleteLoading"
      @confirm="confirmDelete"
    >
      <template #body>
        <p class="text-sm text-[#0f1728]">
          Are you sure you want to delete
          <strong>{{ deletingCourt?.name }}</strong
          >? This cannot be undone.
        </p>
      </template>
    </AppModal>
  </div>
</template>
