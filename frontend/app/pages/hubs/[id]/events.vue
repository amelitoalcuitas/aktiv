<script setup lang="ts">
import { useHubEvents } from '~/composables/useHubEvents';
import type { HubEvent, EventType, DiscountType } from '~/types/hub';

definePageMeta({ middleware: 'auth', layout: 'dashboard-hub' });

const route = useRoute();
const { fetchEvents, createEvent, updateEvent, deleteEvent, toggleEvent } =
  useHubEvents();
const { fetchCourts } = useHubs();
const toast = useToast();

const hubId = computed(() => String(route.params.id));

const manageTabs = computed(() => [
  { label: 'Hub', icon: 'i-heroicons-building-storefront', to: `/hubs/${hubId.value}/edit` },
  { label: 'Courts', icon: 'i-heroicons-squares-2x2', to: `/hubs/${hubId.value}/courts` },
  { label: 'Bookings', icon: 'i-heroicons-calendar-days', to: `/hubs/${hubId.value}/bookings` },
  { label: 'Events', icon: 'i-heroicons-megaphone', to: `/hubs/${hubId.value}/events` },
  { label: 'Reviews', icon: 'i-heroicons-star', to: `/hubs/${hubId.value}/reviews` },
  { label: 'Settings', icon: 'i-heroicons-cog-6-tooth', to: `/hubs/${hubId.value}/settings` }
]);

const events = ref<HubEvent[]>([]);
const eventsLoading = ref(false);

onMounted(async () => {
  await Promise.all([loadEvents(), loadCourts()]);
});

async function loadEvents() {
  eventsLoading.value = true;
  try {
    events.value = await fetchEvents(hubId.value);
  } finally {
    eventsLoading.value = false;
  }
}

// ── Courts for the hub (for affected_courts picker) ──────────────────

const hubCourts = ref<
  Array<{ id: string; name: string; price_per_hour: string }>
>([]);

async function loadCourts() {
  try {
    hubCourts.value = await fetchCourts(hubId.value);
  } catch {
    hubCourts.value = [];
  }
}

const courtOptions = computed(() =>
  hubCourts.value.map((c) => ({
    label: c.name,
    value: c.id
  }))
);

// ── Form ──────────────────────────────────────────────────────────────────────

const isFormOpen = ref(false);
const editingEvent = ref<HubEvent | null>(null);
const formLoading = ref(false);

const EVENT_TYPE_OPTIONS = [
  { label: 'Closure', value: 'closure' },
  { label: 'Promo', value: 'promo' },
  { label: 'Announcement', value: 'announcement' }
] as const;

const DISCOUNT_TYPE_OPTIONS = [
  { label: '% Percent', value: 'percent' },
  { label: '₱ Flat', value: 'flat' }
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
  is_active: true
});

function addCourtDiscountRow() {
  const firstUnused = hubCourts.value.find(
    (c) => !form.court_discounts.some((r) => r.court_id === c.id)
  );
  form.court_discounts.push({
    court_id: firstUnused?.id ?? '',
    discount_type: 'percent',
    discount_value: ''
  });
}

function removeCourtDiscountRow(idx: number) {
  form.court_discounts.splice(idx, 1);
}

function duplicateCourtDiscountRow(idx: number) {
  const row = form.court_discounts[idx];
  if (!row) return;
  const firstUnused = hubCourts.value.find(
    (c) => !form.court_discounts.some((r) => r.court_id === c.id)
  );
  form.court_discounts.splice(idx + 1, 0, {
    court_id: firstUnused?.id ?? row.court_id,
    discount_type: row.discount_type,
    discount_value: row.discount_value
  });
}

function originalPrice(courtId: string): number {
  return parseFloat(
    hubCourts.value.find((c) => c.id === courtId)?.price_per_hour ?? '0'
  );
}

function discountedPrice(
  courtId: string,
  discountType: DiscountType,
  discountValue: string
): number | null {
  const orig = originalPrice(courtId);
  if (!orig || !discountValue) return null;
  const val = parseFloat(discountValue);
  if (isNaN(val)) return null;
  if (discountType === 'percent')
    return Math.max(0, orig * (1 - Math.min(val, 100) / 100));
  return Math.max(0, orig - val);
}

function formatPrice(n: number): string {
  return `₱${n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

const formErrors = reactive({
  title: '',
  event_type: '',
  date_from: '',
  date_to: '',
  discount_type: '',
  discount_value: ''
});

const courtDiscountErrors = ref<string[]>([]);

function clearFormErrors() {
  formErrors.title = '';
  formErrors.event_type = '';
  formErrors.date_from = '';
  formErrors.date_to = '';
  formErrors.discount_type = '';
  formErrors.discount_value = '';
  courtDiscountErrors.value = [];
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
      formErrors.discount_value =
        'A valid discount value is required for promos.';
      valid = false;
    }
  }

  if (form.event_type === 'promo' && form.court_discounts.length > 0) {
    courtDiscountErrors.value = form.court_discounts.map((row) =>
      !row.discount_value || isNaN(parseFloat(row.discount_value))
        ? 'Value is required.'
        : ''
    );
    if (courtDiscountErrors.value.some((e) => e)) valid = false;
  }

  return valid;
}

function openAdd() {
  editingEvent.value = null;
  clearFormErrors();
  const today = toDateStr(new Date());
  Object.assign(form, {
    title: '',
    description: '',
    event_type: 'announcement',
    date_from: today,
    date_to: today,
    time_from: '',
    time_to: '',
    discount_type: 'percent',
    discount_value: '',
    affected_courts: null,
    court_discounts: [],
    is_active: true
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
    court_discounts:
      event.court_discounts?.map((r) => ({
        court_id: r.court_id,
        discount_type: r.discount_type,
        discount_value: String(r.discount_value)
      })) ?? [],
    is_active: event.is_active
  });
  isFormOpen.value = true;
}

async function submitForm() {
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
      discount_type:
        form.event_type === 'promo' && !form.court_discounts.length
          ? form.discount_type
          : null,
      discount_value:
        form.event_type === 'promo' && !form.court_discounts.length
          ? parseFloat(form.discount_value)
          : null,
      affected_courts: form.affected_courts?.length
        ? form.affected_courts
        : null,
      court_discounts:
        form.event_type === 'promo' && form.court_discounts.length
          ? form.court_discounts.map((r) => ({
              court_id: r.court_id,
              discount_type: r.discount_type,
              discount_value: parseFloat(r.discount_value)
            }))
          : null,
      is_active: form.is_active
    };

    if (editingEvent.value) {
      await updateEvent(hubId.value, editingEvent.value.id, payload);
      toast.add({ title: 'Event updated', color: 'success' });
    } else {
      await createEvent(hubId.value, payload);
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
  if (!deletingEvent.value) return;
  deleteLoading.value = true;
  try {
    await deleteEvent(hubId.value, deletingEvent.value.id);
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
  togglingEventId.value = event.id;
  try {
    const updated = await toggleEvent(hubId.value, event.id);
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
  set: (d: Date) => {
    form.date_from = toDateStr(d);
  }
});

const dateToObj = computed({
  get: () => toDateObj(form.date_to),
  set: (d: Date) => {
    form.date_to = toDateStr(d);
  }
});

// ── Helpers ────────────────────────────────────────────────────────────────────

const EVENT_TYPE_STYLES: Record<EventType, string> = {
  closure: 'bg-[#fee2e2] text-[#9f1239]',
  promo: 'bg-[#fef9c3] text-[#854d0e]',
  announcement: 'bg-[#dbeafe] text-[#1e40af]'
};

const EVENT_TYPE_LABELS: Record<EventType, string> = {
  closure: 'Closure',
  promo: 'Promo',
  announcement: 'Announcement'
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
  if (event.discount_type === 'percent')
    return `${parseFloat(event.discount_value)}% off`;
  return `₱${parseFloat(event.discount_value).toFixed(0)} off`;
}
</script>

<template>
  <div>
    <HubTabNav :tabs="manageTabs" />

    <div class="mx-auto w-full max-w-[1400px] px-4 py-8 md:px-6">
      <!-- Header -->
      <div class="mb-6 flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-[#0f1728]">Events</h1>
          <p class="mt-1 text-sm text-[#64748b]">
            Manage closures, promos, and announcements.
          </p>
        </div>
        <UButton
          icon="i-heroicons-plus"
          class="bg-[#004e89] font-semibold hover:bg-[#003d6b] shrink-0 whitespace-nowrap"
          @click="openAdd"
        >
          Add Event
        </UButton>
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
        <UIcon
          name="i-heroicons-calendar-days"
          class="mx-auto h-10 w-10 text-[#c8d5e0]"
        />
        <h3 class="mt-3 text-sm font-semibold text-[#0f1728]">No events yet</h3>
        <p class="mt-1 text-xs text-[#64748b]">
          Add closures, promos, or announcements to inform your customers.
        </p>
        <UButton
          icon="i-heroicons-plus"
          class="mt-4 bg-[#004e89] hover:bg-[#003d6b]"
          size="sm"
          @click="openAdd"
        >
          Add Event
        </UButton>
      </div>

      <!-- Events list -->
      <div v-else class="space-y-0 overflow-hidden rounded-2xl border border-[#dbe4ef] bg-white">
        <!-- Desktop table header -->
        <div class="hidden sm:grid sm:grid-cols-[140px_1fr_160px_100px_140px_80px] border-b border-[#dbe4ef] bg-[#f8fafc] px-4 py-3 text-xs font-medium text-[#64748b]">
          <span>Type</span>
          <span>Title</span>
          <span>Date Range</span>
          <span>Discount</span>
          <span>Status</span>
          <span class="text-right">Actions</span>
        </div>

        <div
          v-for="event in events"
          :key="event.id"
          class="border-b border-[#f0f4f8] last:border-0 hover:bg-[#fafcff]"
        >
          <!-- Mobile card layout -->
          <div class="flex items-start justify-between gap-3 p-4 sm:hidden">
            <div class="min-w-0 flex-1 space-y-1">
              <div class="flex items-center gap-2 flex-wrap">
                <span
                  class="rounded-full px-2 py-0.5 text-xs font-medium shrink-0"
                  :class="EVENT_TYPE_STYLES[event.event_type]"
                >
                  {{ EVENT_TYPE_LABELS[event.event_type] }}
                </span>
                <span
                  class="rounded-full px-2 py-0.5 text-xs font-medium shrink-0"
                  :class="event.is_active ? 'bg-[#daf7d0] text-[#1e6a0f]' : 'bg-[#fee2e2] text-[#9f1239]'"
                >
                  {{ event.is_active ? 'Active' : 'Inactive' }}
                </span>
              </div>
              <p class="font-medium text-sm text-[#0f1728] truncate">{{ event.title }}</p>
              <p v-if="event.description" class="text-xs text-[#64748b] truncate">{{ event.description }}</p>
              <p class="text-xs text-[#94a3b8]">{{ formatDateRange(event.date_from, event.date_to) }}</p>
            </div>
            <div class="flex shrink-0 flex-col items-end gap-2">
              <div class="flex items-center gap-1">
                <UButton icon="i-heroicons-pencil-square" color="neutral" variant="ghost" size="sm" @click="openEdit(event)" />
                <UButton icon="i-heroicons-trash" color="error" variant="ghost" size="sm" @click="openDelete(event)" />
              </div>
              <USwitch
                :model-value="event.is_active"
                :disabled="togglingEventId === event.id"
                @update:model-value="handleToggle(event)"
              />
            </div>
          </div>

          <!-- Desktop row layout -->
          <div class="hidden sm:grid sm:grid-cols-[140px_1fr_160px_100px_140px_80px] items-center px-4 py-3 text-sm">
            <div>
              <span
                class="rounded-full px-2 py-0.5 text-xs font-medium"
                :class="EVENT_TYPE_STYLES[event.event_type]"
              >
                {{ EVENT_TYPE_LABELS[event.event_type] }}
              </span>
            </div>
            <div class="min-w-0 pr-3">
              <p class="truncate font-medium text-[#0f1728]" :title="event.title">{{ event.title }}</p>
              <p v-if="event.description" class="truncate text-xs text-[#64748b]" :title="event.description">{{ event.description }}</p>
            </div>
            <div class="text-[#64748b]">{{ formatDateRange(event.date_from, event.date_to) }}</div>
            <div class="text-[#64748b]">{{ formatDiscount(event) }}</div>
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
            <div class="flex items-center justify-end gap-1">
              <UButton icon="i-heroicons-pencil-square" color="neutral" variant="ghost" size="sm" @click="openEdit(event)" />
              <UButton icon="i-heroicons-trash" color="error" variant="ghost" size="sm" @click="openDelete(event)" />
            </div>
          </div>
        </div>
      </div>
    </div>

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
          <UFormField
            label="Event Type"
            required
            :error="formErrors.event_type || undefined"
          >
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
                <input
                  type="radio"
                  class="sr-only"
                  :value="opt.value"
                  v-model="form.event_type"
                />
                {{ opt.label }}
              </label>
            </div>
          </UFormField>

          <UFormField
            label="Title"
            required
            :error="formErrors.title || undefined"
          >
            <UInput
              v-model="form.title"
              placeholder="e.g. Holiday Closure"
              class="w-full"
              maxlength="100"
            />
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
            <UFormField
              label="Start Date"
              required
              :error="formErrors.date_from || undefined"
            >
              <AppDatePicker
                v-model="dateFromObj"
                variant="nav"
                :label="dateFromObj.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })"
              />
            </UFormField>
            <UFormField
              label="End Date"
              required
              :error="formErrors.date_to || undefined"
            >
              <AppDatePicker
                v-model="dateToObj"
                variant="nav"
                :label="dateToObj.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })"
              />
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
                  Per Court Discounts
                </p>
                <UButton
                  v-if="form.court_discounts.length < hubCourts.length"
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
                class="rounded-lg border border-[#dbe4ef] bg-[#f8fafc] p-3 space-y-2"
              >
                <!-- Row 1: Court selector + actions -->
                <div class="flex items-center gap-2">
                  <span class="shrink-0 text-sm font-semibold text-[#0f1728]"
                    >Court:</span
                  >
                  <USelect
                    v-model="row.court_id"
                    :items="
                      courtOptions.filter(
                        (o) =>
                          o.value === row.court_id ||
                          !form.court_discounts.some(
                            (r, i) => i !== idx && r.court_id === o.value
                          )
                      )
                    "
                    class="flex-1 min-w-0"
                  />
                  <UButton
                    icon="i-heroicons-document-duplicate"
                    color="neutral"
                    variant="ghost"
                    size="sm"
                    :disabled="form.court_discounts.length >= hubCourts.length"
                    class="shrink-0"
                    @click="duplicateCourtDiscountRow(idx)"
                  />
                  <UButton
                    icon="i-heroicons-trash"
                    color="error"
                    variant="ghost"
                    size="sm"
                    class="shrink-0"
                    @click="removeCourtDiscountRow(idx)"
                  />
                </div>
                <!-- Row 2: Type + Value -->
                <div class="grid grid-cols-2 gap-2">
                  <UFormField label="Type">
                    <USelect
                      v-model="row.discount_type"
                      :items="[...DISCOUNT_TYPE_OPTIONS]"
                      class="w-full"
                    />
                  </UFormField>
                  <UFormField
                    label="Value"
                    required
                    :error="courtDiscountErrors[idx] || undefined"
                  >
                    <UInput
                      v-model="row.discount_value"
                      type="number"
                      min="0"
                      step="0.01"
                      :placeholder="
                        row.discount_type === 'percent' ? '20' : '100'
                      "
                      class="w-full"
                      @input="courtDiscountErrors[idx] = ''"
                    />
                  </UFormField>
                </div>
                <!-- Row 3: Price preview -->
                <div class="justify-end flex">
                  <template
                    v-if="
                      discountedPrice(
                        row.court_id,
                        row.discount_type,
                        row.discount_value
                      ) !== null
                    "
                  >
                    <span class="text-[#64748b] line-through mr-1">{{
                      formatPrice(originalPrice(row.court_id))
                    }}</span>
                    <span class="font-semibold text-[#166534]"
                      >{{
                        formatPrice(
                          discountedPrice(
                            row.court_id,
                            row.discount_type,
                            row.discount_value
                          )!
                        )
                      }}/hr</span
                    >
                  </template>
                  <span v-else class="text-[#94a3b8]">—</span>
                </div>
              </div>

              <p
                v-if="form.court_discounts.length === 0"
                class="text-xs text-[#64748b]"
              >
                No per-court discounts — fill in the fields below to apply one
                discount to all courts.
              </p>
            </div>

            <!-- Global discount (only shown when no per-court rows) -->
            <template v-if="form.court_discounts.length === 0">
              <UFormField
                label="Discount Type"
                required
                :error="formErrors.discount_type || undefined"
              >
                <USelect
                  v-model="form.discount_type"
                  :items="[...DISCOUNT_TYPE_OPTIONS]"
                  class="w-full"
                />
              </UFormField>
              <UFormField
                :label="
                  form.discount_type === 'percent'
                    ? 'Discount (%)'
                    : 'Discount (₱)'
                "
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
          Are you sure you want to delete
          <strong>{{ deletingEvent?.title }}</strong
          >? This cannot be undone.
        </p>
      </template>
    </AppModal>
  </div>
</template>
