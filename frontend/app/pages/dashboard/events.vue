<script setup lang="ts">
import { useHubStore } from '~/stores/hub';
import { useHubEvents } from '~/composables/useHubEvents';
import type { Hub, HubEvent, EventType, DiscountType } from '~/types/hub';

definePageMeta({ layout: 'dashboard', middleware: ['auth', 'admin'] });

const hubStore = useHubStore();
const { fetchEvents, createEvent, updateEvent, deleteEvent, toggleEvent } = useHubEvents();
const toast = useToast();

// ── Hub selector ──────────────────────────────────────────────────────────────

const selectedHubId = ref<string | undefined>(undefined);
const events = ref<HubEvent[]>([]);
const eventsLoading = ref(false);

const hubOptions = computed(() =>
  hubStore.myHubs.map((h: Hub) => ({ label: h.name, value: h.id }))
);

onMounted(async () => {
  await hubStore.fetchMyHubs();
  if (hubStore.myHubs.length) {
    selectedHubId.value = hubStore.myHubs[0]?.id;
    await loadEvents();
  }
});

watch(selectedHubId, async () => {
  events.value = [];
  if (selectedHubId.value) await loadEvents();
});

async function loadEvents() {
  if (!selectedHubId.value) return;
  eventsLoading.value = true;
  try {
    events.value = await fetchEvents(selectedHubId.value);
  } finally {
    eventsLoading.value = false;
  }
}

// ── Courts for the selected hub (for affected_courts picker) ──────────────────

const selectedHub = computed<Hub | undefined>(() =>
  hubStore.myHubs.find((h: Hub) => h.id === selectedHubId.value)
);

const hubCourts = computed<Array<{ id: string; name: string; price_per_hour: string }>>(
  () => (selectedHub.value as any)?.courts ?? []
);

const courtOptions = computed(() =>
  hubCourts.value.map((c) => ({
    label: `${c.name} (₱${parseFloat(c.price_per_hour).toLocaleString('en-PH', { maximumFractionDigits: 0 })}/hr)`,
    value: c.id,
  }))
);

// ── Form ──────────────────────────────────────────────────────────────────────

const isFormOpen = ref(false);
const editingEvent = ref<HubEvent | null>(null);
const formLoading = ref(false);

const EVENT_TYPE_OPTIONS = [
  { label: 'Closure', value: 'closure' },
  { label: 'Promo', value: 'promo' },
  { label: 'Announcement', value: 'announcement' },
] as const;

const DISCOUNT_TYPE_OPTIONS = [
  { label: 'Percent (%)', value: 'percent' },
  { label: 'Flat amount (₱)', value: 'flat' },
] as const;

function toDateObj(str: string): Date {
  if (!str) return new Date();
  const [y, m, d] = str.split('-').map(Number);
  return new Date(y, m - 1, d);
}

function toDateStr(d: Date): string {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

interface CourtDiscountRow {
  court_id: string;
  discount_type: DiscountType;
  discount_value: string;
}

const form = reactive({
  title: '',
  description: '',
  event_type: 'announcement' as EventType,
  date_from: '',
  date_to: '',
  time_from: '',
  time_to: '',
  discount_type: 'percent' as DiscountType,
  discount_value: '' as string,
  affected_courts: null as string[] | null,
  court_discounts: [] as CourtDiscountRow[],
  is_active: true,
});

function addCourtDiscountRow() {
  const firstUnused = hubCourts.value.find(
    (c) => !form.court_discounts.some((r) => r.court_id === c.id)
  );
  form.court_discounts.push({
    court_id: firstUnused?.id ?? '',
    discount_type: 'percent',
    discount_value: '',
  });
}

function removeCourtDiscountRow(idx: number) {
  form.court_discounts.splice(idx, 1);
}

const formErrors = reactive({
  title: '',
  event_type: '',
  date_from: '',
  date_to: '',
  discount_type: '',
  discount_value: '',
});

function clearFormErrors() {
  formErrors.title = '';
  formErrors.event_type = '';
  formErrors.date_from = '';
  formErrors.date_to = '';
  formErrors.discount_type = '';
  formErrors.discount_value = '';
}

function validateForm(): boolean {
  clearFormErrors();
  let valid = true;

  if (!form.title.trim()) {
    formErrors.title = 'Title is required.';
    valid = false;
  }
  if (!form.event_type) {
    formErrors.event_type = 'Event type is required.';
    valid = false;
  }
  if (!form.date_from) {
    formErrors.date_from = 'Start date is required.';
    valid = false;
  }
  if (!form.date_to) {
    formErrors.date_to = 'End date is required.';
    valid = false;
  }
  if (form.date_from && form.date_to && form.date_to < form.date_from) {
    formErrors.date_to = 'End date must be on or after start date.';
    valid = false;
  }
  if (form.event_type === 'promo' && form.court_discounts.length === 0) {
    if (!form.discount_type) {
      formErrors.discount_type = 'Discount type is required for promos.';
      valid = false;
    }
    if (!form.discount_value || isNaN(parseFloat(form.discount_value))) {
      formErrors.discount_value = 'A valid discount value is required for promos.';
      valid = false;
    }
  }

  return valid;
}

function openAdd() {
  editingEvent.value = null;
  clearFormErrors();
  Object.assign(form, {
    title: '',
    description: '',
    event_type: 'announcement',
    date_from: '',
    date_to: '',
    time_from: '',
    time_to: '',
    discount_type: 'percent',
    discount_value: '',
    affected_courts: null,
    court_discounts: [],
    is_active: true,
  });
  isFormOpen.value = true;
}

function openEdit(event: HubEvent) {
  editingEvent.value = event;
  clearFormErrors();
  Object.assign(form, {
    title: event.title,
    description: event.description ?? '',
    event_type: event.event_type,
    date_from: event.date_from,
    date_to: event.date_to,
    time_from: event.time_from ?? '',
    time_to: event.time_to ?? '',
    discount_type: event.discount_type ?? 'percent',
    discount_value: event.discount_value ?? '',
    affected_courts: event.affected_courts ?? null,
    court_discounts: event.court_discounts?.map((r) => ({
      court_id: r.court_id,
      discount_type: r.discount_type,
      discount_value: String(r.discount_value),
    })) ?? [],
    is_active: event.is_active,
  });
  isFormOpen.value = true;
}

async function submitForm() {
  if (!selectedHubId.value) return;
  if (!validateForm()) return;

  formLoading.value = true;
  try {
    const payload = {
      title: form.title.trim(),
      description: form.description.trim() || null,
      event_type: form.event_type,
      date_from: form.date_from,
      date_to: form.date_to,
      time_from: form.time_from || null,
      time_to: form.time_to || null,
      discount_type: (form.event_type === 'promo' && !form.court_discounts.length) ? form.discount_type : null,
      discount_value: (form.event_type === 'promo' && !form.court_discounts.length) ? parseFloat(form.discount_value) : null,
      affected_courts: form.affected_courts?.length ? form.affected_courts : null,
      court_discounts: (form.event_type === 'promo' && form.court_discounts.length)
        ? form.court_discounts.map((r) => ({
            court_id: r.court_id,
            discount_type: r.discount_type,
            discount_value: parseFloat(r.discount_value),
          }))
        : null,
      is_active: form.is_active,
    };

    if (editingEvent.value) {
      await updateEvent(selectedHubId.value, editingEvent.value.id, payload);
      toast.add({ title: 'Event updated', color: 'success' });
    } else {
      await createEvent(selectedHubId.value, payload);
      toast.add({ title: 'Event created', color: 'success' });
    }
    isFormOpen.value = false;
    await loadEvents();
  } catch {
    toast.add({ title: 'Failed to save event', color: 'error' });
  } finally {
    formLoading.value = false;
  }
}

// ── Delete ─────────────────────────────────────────────────────────────────────

const isDeleteOpen = ref(false);
const deletingEvent = ref<HubEvent | null>(null);
const deleteLoading = ref(false);

function openDelete(event: HubEvent) {
  deletingEvent.value = event;
  isDeleteOpen.value = true;
}

async function confirmDelete() {
  if (!selectedHubId.value || !deletingEvent.value) return;
  deleteLoading.value = true;
  try {
    await deleteEvent(selectedHubId.value, deletingEvent.value.id);
    toast.add({ title: 'Event deleted', color: 'success' });
    isDeleteOpen.value = false;
    await loadEvents();
  } catch {
    toast.add({ title: 'Failed to delete event', color: 'error' });
  } finally {
    deleteLoading.value = false;
  }
}

// ── Toggle active ──────────────────────────────────────────────────────────────

const togglingEventId = ref<string | null>(null);

async function handleToggle(event: HubEvent) {
  if (!selectedHubId.value) return;
  togglingEventId.value = event.id;
  try {
    const updated = await toggleEvent(selectedHubId.value, event.id);
    const idx = events.value.findIndex((e) => e.id === event.id);
    if (idx !== -1) events.value[idx] = updated;
  } catch {
    toast.add({ title: 'Failed to update status', color: 'error' });
  } finally {
    togglingEventId.value = null;
  }
}

// ── Date picker bridges ────────────────────────────────────────────────────────

const dateFromObj = computed({
  get: () => toDateObj(form.date_from),
  set: (d: Date) => { form.date_from = toDateStr(d); },
});

const dateToObj = computed({
  get: () => toDateObj(form.date_to),
  set: (d: Date) => { form.date_to = toDateStr(d); },
});

// ── Helpers ────────────────────────────────────────────────────────────────────

const EVENT_TYPE_STYLES: Record<EventType, string> = {
  closure: 'bg-[#fee2e2] text-[#9f1239]',
  promo: 'bg-[#fef9c3] text-[#854d0e]',
  announcement: 'bg-[#dbeafe] text-[#1e40af]',
};

const EVENT_TYPE_LABELS: Record<EventType, string> = {
  closure: 'Closure',
  promo: 'Promo',
  announcement: 'Announcement',
};

function formatDateRange(from: string, to: string): string {
  if (from === to) return from;
  return `${from} → ${to}`;
}

function formatDiscount(event: HubEvent): string {
  if (event.event_type !== 'promo') return '—';
  if (event.court_discounts?.length) {
    return `${event.court_discounts.length} court${event.court_discounts.length > 1 ? 's' : ''}`;
  }
  if (!event.discount_value) return '—';
  if (event.discount_type === 'percent') return `${parseFloat(event.discount_value)}% off`;
  return `₱${parseFloat(event.discount_value).toFixed(0)} off`;
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-[#0f1728]">Events</h1>
        <p class="mt-1 text-sm text-[#64748b]">
          Manage closures, promos, and announcements for your hubs.
        </p>
      </div>
      <UButton
        v-if="selectedHubId"
        icon="i-heroicons-plus"
        class="bg-[#004e89] font-semibold hover:bg-[#003d6b]"
        @click="openAdd"
      >
        Add Event
      </UButton>
    </div>

    <!-- Hubs loading -->
    <div
      v-if="!hubStore.initialized || hubStore.loading"
      class="flex items-center gap-2 text-[#64748b]"
    >
      <UIcon name="i-heroicons-arrow-path" class="h-5 w-5 animate-spin" />
      <span class="text-sm">Loading...</span>
    </div>

    <!-- No hubs -->
    <div
      v-else-if="!hubStore.myHubs.length"
      class="rounded-2xl border border-dashed border-[#dbe4ef] bg-white p-12 text-center"
    >
      <UIcon name="i-heroicons-building-office-2" class="mx-auto h-12 w-12 text-[#c8d5e0]" />
      <h3 class="mt-4 text-base font-semibold text-[#0f1728]">No hubs yet</h3>
      <p class="mt-1 text-sm text-[#64748b]">Create a hub first before adding events.</p>
      <UButton to="/hubs/create" icon="i-heroicons-plus" class="mt-5 bg-[#004e89] hover:bg-[#003d6b]">
        Create Hub
      </UButton>
    </div>

    <template v-else>
      <!-- Hub selector -->
      <div class="mb-6 flex items-center gap-3">
        <label class="text-sm font-medium text-[#0f1728]">Hub:</label>
        <USelect v-model="selectedHubId" :items="hubOptions" class="w-64" />
      </div>

      <!-- Events loading -->
      <div v-if="eventsLoading" class="flex items-center gap-2 text-[#64748b]">
        <UIcon name="i-heroicons-arrow-path" class="h-5 w-5 animate-spin" />
        <span class="text-sm">Loading events…</span>
      </div>

      <!-- Empty state -->
      <div
        v-else-if="!events.length"
        class="rounded-2xl border border-dashed border-[#dbe4ef] bg-white p-10 text-center"
      >
        <UIcon name="i-heroicons-calendar-days" class="mx-auto h-10 w-10 text-[#c8d5e0]" />
        <h3 class="mt-3 text-sm font-semibold text-[#0f1728]">No events yet</h3>
        <p class="mt-1 text-xs text-[#64748b]">
          Add closures, promos, or announcements to inform your customers.
        </p>
        <UButton icon="i-heroicons-plus" class="mt-4 bg-[#004e89] hover:bg-[#003d6b]" size="sm" @click="openAdd">
          Add Event
        </UButton>
      </div>

      <!-- Events table -->
      <div v-else class="overflow-hidden rounded-2xl border border-[#dbe4ef] bg-white">
        <table class="w-full text-sm">
          <thead class="border-b border-[#dbe4ef] bg-[#f8fafc] text-[#64748b]">
            <tr>
              <th class="px-4 py-3 text-left font-medium">Type</th>
              <th class="px-4 py-3 text-left font-medium">Title</th>
              <th class="hidden px-4 py-3 text-left font-medium sm:table-cell">Date Range</th>
              <th class="hidden px-4 py-3 text-left font-medium md:table-cell">Discount</th>
              <th class="px-4 py-3 text-left font-medium">Status</th>
              <th class="px-4 py-3 text-right font-medium">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#f0f4f8]">
            <tr v-for="event in events" :key="event.id" class="hover:bg-[#fafcff]">
              <td class="px-4 py-3">
                <span
                  class="rounded-full px-2 py-0.5 text-xs font-medium"
                  :class="EVENT_TYPE_STYLES[event.event_type]"
                >
                  {{ EVENT_TYPE_LABELS[event.event_type] }}
                </span>
              </td>
              <td class="max-w-[200px] px-4 py-3 font-medium text-[#0f1728]">
                <p class="truncate" :title="event.title">{{ event.title }}</p>
                <p v-if="event.description" class="truncate text-xs text-[#64748b]" :title="event.description">
                  {{ event.description }}
                </p>
              </td>
              <td class="hidden px-4 py-3 text-[#64748b] sm:table-cell">
                {{ formatDateRange(event.date_from, event.date_to) }}
              </td>
              <td class="hidden px-4 py-3 text-[#64748b] md:table-cell">
                {{ formatDiscount(event) }}
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <USwitch
                    :model-value="event.is_active"
                    :disabled="togglingEventId === event.id"
                    @update:model-value="handleToggle(event)"
                  />
                  <span
                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="event.is_active ? 'bg-[#daf7d0] text-[#1e6a0f]' : 'bg-[#fee2e2] text-[#9f1239]'"
                  >
                    {{ event.is_active ? 'Active' : 'Inactive' }}
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
                    @click="openEdit(event)"
                  />
                  <UButton
                    icon="i-heroicons-trash"
                    color="error"
                    variant="ghost"
                    size="sm"
                    @click="openDelete(event)"
                  />
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- Event Form Modal -->
    <AppModal
      v-model:open="isFormOpen"
      :title="editingEvent ? 'Edit Event' : 'Add Event'"
      :ui="{ content: 'max-w-lg' }"
      :confirm="editingEvent ? 'Save Changes' : 'Add Event'"
      :confirm-loading="formLoading"
      @confirm="submitForm"
    >
      <template #body>
        <div class="space-y-4">
          <UFormField label="Event Type" required :error="formErrors.event_type || undefined">
            <div class="flex gap-2 flex-wrap pt-1">
              <label
                v-for="opt in EVENT_TYPE_OPTIONS"
                :key="opt.value"
                class="flex cursor-pointer items-center gap-1.5 rounded-full border px-3 py-1 text-sm font-medium transition"
                :class="
                  form.event_type === opt.value
                    ? 'border-[#004e89] bg-[#e8f0f8] text-[#004e89]'
                    : 'border-[#dbe4ef] text-[#64748b] hover:border-[#004e89]'
                "
              >
                <input type="radio" class="sr-only" :value="opt.value" v-model="form.event_type" />
                {{ opt.label }}
              </label>
            </div>
          </UFormField>

          <UFormField label="Title" required :error="formErrors.title || undefined">
            <UInput v-model="form.title" placeholder="e.g. Holiday Closure" class="w-full" maxlength="100" />
          </UFormField>

          <UFormField label="Description (optional)">
            <UTextarea
              v-model="form.description"
              placeholder="Visible to customers on the hub page"
              class="w-full"
              :rows="2"
              maxlength="500"
            />
          </UFormField>

          <div class="grid grid-cols-2 gap-3">
            <UFormField label="Start Date" required :error="formErrors.date_from || undefined">
              <AppDatePicker v-model="dateFromObj" class="w-full" />
            </UFormField>
            <UFormField label="End Date" required :error="formErrors.date_to || undefined">
              <AppDatePicker v-model="dateToObj" class="w-full" />
            </UFormField>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <UFormField label="Start Time (optional)">
              <UInput v-model="form.time_from" type="time" class="w-full" />
            </UFormField>
            <UFormField label="End Time (optional)">
              <UInput v-model="form.time_to" type="time" class="w-full" />
            </UFormField>
          </div>

          <!-- Promo-only fields -->
          <template v-if="form.event_type === 'promo'">
            <!-- Per-court discounts -->
            <div class="space-y-2">
              <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-[#0f1728]">
                  Court Discounts
                  <span class="ml-1 text-xs font-normal text-[#64748b]">(leave empty to apply one discount to all courts)</span>
                </p>
                <UButton
                  v-if="form.court_discounts.length < hubCourts.length"
                  size="xs"
                  variant="ghost"
                  color="primary"
                  icon="i-heroicons-plus"
                  @click="addCourtDiscountRow"
                >
                  Add Court
                </UButton>
              </div>

              <div
                v-for="(row, idx) in form.court_discounts"
                :key="idx"
                class="flex items-end gap-2 rounded-lg border border-[#dbe4ef] bg-[#f8fafc] p-2"
              >
                <UFormField label="Court" class="flex-1 min-w-0">
                  <USelect
                    v-model="row.court_id"
                    :items="courtOptions.filter(o => o.value === row.court_id || !form.court_discounts.some((r, i) => i !== idx && r.court_id === o.value))"
                    class="w-full"
                  />
                </UFormField>
                <UFormField label="Type" class="w-32 shrink-0">
                  <USelect v-model="row.discount_type" :items="[...DISCOUNT_TYPE_OPTIONS]" class="w-full" />
                </UFormField>
                <UFormField :label="row.discount_type === 'percent' ? 'Value (%)' : 'Value (₱)'" class="w-24 shrink-0">
                  <UInput
                    v-model="row.discount_value"
                    type="number"
                    min="0"
                    step="0.01"
                    :placeholder="row.discount_type === 'percent' ? '20' : '100'"
                    class="w-full"
                  />
                </UFormField>
                <UButton
                  icon="i-heroicons-trash"
                  color="error"
                  variant="ghost"
                  size="sm"
                  class="mb-0.5 shrink-0"
                  @click="removeCourtDiscountRow(idx)"
                />
              </div>

              <p v-if="form.court_discounts.length === 0" class="text-xs text-[#64748b]">
                No per-court discounts — fill in the fields below to apply one discount to all courts.
              </p>
            </div>

            <!-- Global discount (only shown when no per-court rows) -->
            <template v-if="form.court_discounts.length === 0">
              <UFormField label="Discount Type" required :error="formErrors.discount_type || undefined">
                <USelect v-model="form.discount_type" :items="[...DISCOUNT_TYPE_OPTIONS]" class="w-full" />
              </UFormField>
              <UFormField
                :label="form.discount_type === 'percent' ? 'Discount (%)' : 'Discount (₱)'"
                required
                :error="formErrors.discount_value || undefined"
              >
                <UInput
                  v-model="form.discount_value"
                  type="number"
                  min="0"
                  step="0.01"
                  :placeholder="form.discount_type === 'percent' ? '20' : '100'"
                  class="w-full"
                />
              </UFormField>
            </template>
          </template>

          <label class="flex cursor-pointer items-center gap-2 text-sm">
            <USwitch v-model="form.is_active" />
            <span class="font-medium text-[#0f1728]">Active</span>
          </label>
        </div>
      </template>
    </AppModal>

    <!-- Delete Confirm Modal -->
    <AppModal
      v-model:open="isDeleteOpen"
      title="Delete Event"
      :ui="{ content: 'max-w-sm' }"
      confirm="Delete"
      confirm-color="error"
      :confirm-loading="deleteLoading"
      @confirm="confirmDelete"
    >
      <template #body>
        <p class="text-sm text-[#0f1728]">
          Are you sure you want to delete <strong>{{ deletingEvent?.title }}</strong>? This cannot be undone.
        </p>
      </template>
    </AppModal>
  </div>
</template>
