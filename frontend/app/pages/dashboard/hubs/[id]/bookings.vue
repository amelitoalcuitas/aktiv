<script setup lang="ts">
import type { Court } from '~/types/hub';
import type { BookingDetail, BookingStatus } from '~/types/booking';
import type { OpenPlaySession } from '~/types/openPlay';
import { useHubs } from '~/composables/useHubs';
import { useOwnerBookings } from '~/composables/useOwnerBookings';
import { useOwnerOpenPlay } from '~/composables/useOwnerOpenPlay';
import OpenPlayOwnerModal from '~/components/openPlay/OpenplayOwnerModal.vue';

definePageMeta({ middleware: 'owner-hub', layout: 'dashboard-hub' });

const route = useRoute();
const { fetchCourts, fetchHub } = useHubs();
const {
  fetchHubBookings,
  confirmBooking,
  rejectBooking,
  cancelBooking,
  createWalkIn,
  updateBooking
} = useOwnerBookings();
const { fetchSession } = useOwnerOpenPlay();
const toast = useToast();

const hubId = computed(() => String(route.params.id));

const manageTabs = computed(() => [
  {
    label: 'Hub',
    icon: 'i-heroicons-building-storefront',
    to: `/dashboard/hubs/${hubId.value}/edit`
  },
  {
    label: 'Courts',
    icon: 'i-heroicons-squares-2x2',
    to: `/dashboard/hubs/${hubId.value}/courts`
  },
  {
    label: 'Bookings',
    icon: 'i-heroicons-calendar-days',
    to: `/dashboard/hubs/${hubId.value}/bookings`
  },
  {
    label: 'Events',
    icon: 'i-heroicons-megaphone',
    to: `/dashboard/hubs/${hubId.value}/events`
  },
  {
    label: 'Reviews',
    icon: 'i-heroicons-star',
    to: `/dashboard/hubs/${hubId.value}/reviews`
  },
  {
    label: 'Settings',
    icon: 'i-heroicons-cog-6-tooth',
    to: `/dashboard/hubs/${hubId.value}/settings`
  }
]);

// ── View mode ─────────────────────────────────────────────────
const viewMode = ref<'table' | 'calendar'>('calendar');
const selectedDate = ref(new Date());
const calendarSlot = ref<{
  date: string;
  hour: number;
  courtId?: string;
} | null>(null);

// ── Hub data ──────────────────────────────────────────────────
import type { Hub } from '~/types/hub';
const hubData = ref<Hub | null>(null);

const gridMinTime = computed(() => {
  const oh = hubData.value?.operating_hours;
  if (!oh?.length) return '06:00';
  const open = oh.filter((e) => !e.is_closed);
  if (!open.length) return '06:00';
  return open.reduce(
    (min, e) => (e.opens_at < min ? e.opens_at : min),
    open[0]!.opens_at
  );
});

const gridMaxTime = computed(() => {
  const oh = hubData.value?.operating_hours;
  if (!oh?.length) return '23:00';
  const open = oh.filter((e) => !e.is_closed);
  if (!open.length) return '23:00';
  return open.reduce(
    (max, e) => (e.closes_at > max ? e.closes_at : max),
    open[0]!.closes_at
  );
});

// ── Courts ─────────────────────────────────────────────────
const hubCourts = ref<Court[]>([]);
const courtsLoaded = ref(false);
const courtsLoadFailed = ref(false);

async function loadCourts() {
  try {
    courtsLoadFailed.value = false;
    hubCourts.value = await fetchCourts(hubId.value);
  } catch {
    courtsLoadFailed.value = true;
    toast.add({ title: 'Failed to load courts', color: 'error' });
  } finally {
    courtsLoaded.value = true;
  }
}

// ── Bookings ──────────────────────────────────────────────────
const bookingsLoading = ref(false);
const allBookings = ref<BookingDetail[]>([]);
const tableDate = ref(new Date());
let bookingsLoadRequestId = 0;

function formatDateString(date: Date): string {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

const tableDateStr = computed(() => formatDateString(tableDate.value));

const tableDateLabel = computed(() =>
  tableDate.value.toLocaleDateString('en-PH', {
    weekday: 'long',
    month: 'long',
    day: 'numeric'
  })
);

async function loadBookings() {
  const requestId = ++bookingsLoadRequestId;
  bookingsLoading.value = true;
  try {
    const date =
      viewMode.value === 'table' ? tableDate.value : selectedDate.value;
    const dayStr = formatDateString(date);
    const bookings = await fetchHubBookings(hubId.value, {
      date_from: dayStr,
      date_to: dayStr
    });
    if (requestId !== bookingsLoadRequestId) return;

    allBookings.value = bookings;
    syncOpenPlaySessionsFromBookings(bookings);
  } catch {
    toast.add({ title: 'Failed to load bookings', color: 'error' });
  } finally {
    if (requestId === bookingsLoadRequestId) {
      bookingsLoading.value = false;
    }
  }
}

// ── Filters ───────────────────────────────────────────────────
const statusFilter = ref<(BookingStatus | 'expired')[]>([]);
const courtFilter = ref<string[]>([]);

const STATUS_OPTIONS: { label: string; value: BookingStatus | 'expired' }[] = [
  { label: 'Pending Payment', value: 'pending_payment' },
  { label: 'Receipt Sent', value: 'payment_sent' },
  { label: 'Confirmed', value: 'confirmed' },
  { label: 'Cancelled', value: 'cancelled' },
  { label: 'Expired', value: 'expired' },
  { label: 'Completed', value: 'completed' }
];

const courtFilterOptions = computed(() =>
  hubCourts.value.map((c) => ({ label: c.name, value: c.id as string }))
);

const filteredBookings = computed(() => {
  let list = allBookings.value;
  if (viewMode.value === 'table') {
    const ds = tableDateStr.value;
    list = list.filter((b) => formatDateString(new Date(b.start_time)) === ds);
  }
  if (statusFilter.value.length > 0)
    list = list.filter((b) => statusFilter.value.includes(effectiveStatus(b)));
  if (courtFilter.value.length > 0)
    list = list.filter((b) => courtFilter.value.includes(b.court_id));
  return list;
});

const filteredCourts = computed(() => {
  if (courtFilter.value.length === 0) return hubCourts.value;
  return hubCourts.value.filter((c) => courtFilter.value.includes(c.id));
});

// ── Init ──────────────────────────────────────────────────────
const { apiFetch } = useApi();

onMounted(async () => {
  await Promise.all([
    loadBookings(),
    loadCourts(),
    fetchHub(hubId.value).then((h) => {
      hubData.value = h;
    })
  ]);

  const bookingIdRaw = Array.isArray(route.query.bookingId)
    ? route.query.bookingId[0]
    : route.query.bookingId;
  if (bookingIdRaw) {
    try {
      const res = await apiFetch<{ data: BookingDetail }>(
        `/dashboard/hubs/${hubId.value}/bookings/${bookingIdRaw}`
      );
      openDetails(res.data);
    } catch {
      // booking not found or not accessible — silently ignore
    }
  }
});

watch(
  () => route.query.bookingId,
  async (bookingIdRaw) => {
    if (!bookingIdRaw) return;
    try {
      const res = await apiFetch<{ data: BookingDetail }>(
        `/dashboard/hubs/${hubId.value}/bookings/${bookingIdRaw}`
      );
      openDetails(res.data);
    } catch {
      // booking not found or not accessible — silently ignore
    }
  }
);

watch(selectedDate, async () => {
  if (viewMode.value === 'calendar') {
    await loadBookings();
  }
});

watch(tableDate, async () => {
  if (viewMode.value === 'table') {
    await loadBookings();
  }
});

watch(viewMode, async () => {
  await loadBookings();
});

// ── Auto-reload on real-time booking events ────────────────────
const { $echo } = useNuxtApp();
// eslint-disable-next-line @typescript-eslint/no-explicit-any
let hubChannel: any = null;

onMounted(() => {
  hubChannel = ($echo as any).channel(`hub.${hubId.value}`);
  hubChannel.listen('.booking.slot.updated', () => {
    loadBookings();
  });
});

onUnmounted(() => {
  ($echo as any).leaveChannel(`hub.${hubId.value}`);
  hubChannel = null;
});

// ── Confirm ───────────────────────────────────────────────────
const confirmingId = ref<string | null>(null);

async function handleConfirm(booking: BookingDetail) {
  confirmingId.value = booking.id;
  try {
    const updated = await confirmBooking(hubId.value, booking.id);
    replaceBookingInList(updated);
    toast.add({ title: 'Booking confirmed', color: 'success' });
  } catch {
    toast.add({ title: 'Failed to confirm booking', color: 'error' });
  } finally {
    confirmingId.value = null;
  }
}

// ── Reject ────────────────────────────────────────────────────
const isRejectOpen = ref(false);
const rejectTargetBooking = ref<BookingDetail | null>(null);
const rejectNote = ref('');
const rejectError = ref('');
const rejectingId = ref<string | null>(null);

function openReject(booking: BookingDetail) {
  rejectTargetBooking.value = booking;
  rejectNote.value = '';
  rejectError.value = '';
  isRejectOpen.value = true;
}

async function submitReject() {
  if (!rejectTargetBooking.value) return;
  if (!rejectNote.value.trim()) {
    rejectError.value = 'Please provide a rejection reason.';
    return;
  }
  rejectingId.value = rejectTargetBooking.value.id;
  try {
    const updated = await rejectBooking(
      hubId.value,
      rejectTargetBooking.value.id,
      rejectNote.value.trim()
    );
    replaceBookingInList(updated);
    isRejectOpen.value = false;
    toast.add({
      title: 'Receipt rejected. User can re-upload.',
      color: 'warning'
    });
  } catch {
    toast.add({ title: 'Failed to reject booking', color: 'error' });
  } finally {
    rejectingId.value = null;
  }
}

// ── Cancel ────────────────────────────────────────────────────
const isCancelOpen = ref(false);
const cancelTargetBooking = ref<BookingDetail | null>(null);
const cancellingId = ref<string | null>(null);

function openCancel(booking: BookingDetail) {
  cancelTargetBooking.value = booking;
  isCancelOpen.value = true;
}

async function submitCancel() {
  if (!cancelTargetBooking.value) return;
  cancellingId.value = cancelTargetBooking.value.id;
  try {
    const updated = await cancelBooking(
      hubId.value,
      cancelTargetBooking.value.id
    );
    replaceBookingInList(updated);
    isCancelOpen.value = false;
    toast.add({ title: 'Booking cancelled', color: 'success' });
  } catch {
    toast.add({ title: 'Failed to cancel booking', color: 'error' });
  } finally {
    cancellingId.value = null;
  }
}

// ── Open play session map ─────────────────────────────────────
// Keyed by booking_id, populated when a session is created or participant list opened
const openPlaySessionsMap = ref<Record<string, OpenPlaySession>>({});

function upsertOpenPlaySession(session: OpenPlaySession) {
  openPlaySessionsMap.value[session.booking_id] = session;
}

function syncOpenPlaySessionsFromBookings(bookings: BookingDetail[]) {
  const nextBookingIds = new Set(
    bookings
      .filter((b) => b.session_type === 'open_play' && b.open_play_session_id)
      .map((b) => b.id)
  );

  openPlaySessionsMap.value = Object.fromEntries(
    Object.entries(openPlaySessionsMap.value).filter(([bookingId]) =>
      nextBookingIds.has(bookingId)
    )
  );

  bookings.forEach((booking) => {
    if (booking.open_play_session) {
      upsertOpenPlaySession(booking.open_play_session);
    }
  });
}

// ── Walk-in modal ─────────────────────────────────────────────
const isWalkInOpen = ref(false);

function openWalkIn(slot?: { court: Court; date: Date; hour: number }) {
  if (slot) {
    const y = slot.date.getFullYear();
    const mo = String(slot.date.getMonth() + 1).padStart(2, '0');
    const d = String(slot.date.getDate()).padStart(2, '0');
    calendarSlot.value = {
      date: `${y}-${mo}-${d}`,
      hour: slot.hour,
      courtId: slot.court.id
    };
  } else {
    calendarSlot.value = null;
  }
  isWalkInOpen.value = true;
}

function onWalkInCreated(booking: BookingDetail) {
  allBookings.value.unshift(booking);
}

function onOpenPlayCreated(session: OpenPlaySession) {
  upsertOpenPlaySession(session);
  // The booking embedded in the session response has the same shape as BookingDetail
  if (session.booking) {
    allBookings.value.unshift(session.booking as unknown as BookingDetail);
  }
}

// ── Owner open play modal ─────────────────────────────────────
const isOpenPlayModalOpen = ref(false);
const selectedOpenPlaySessionId = ref<string | null>(null);

async function openOpenPlayModal(booking: BookingDetail) {
  if (!booking.open_play_session_id) return;

  selectedOpenPlaySessionId.value = booking.open_play_session_id;
  isOpenPlayModalOpen.value = true;

  try {
    const session = await fetchSession(
      hubId.value,
      booking.open_play_session_id
    );
    upsertOpenPlaySession(session);
  } catch {
    toast.add({ title: 'Failed to load open play session', color: 'error' });
  }
}

function onOpenPlayUpdated(session: OpenPlaySession) {
  upsertOpenPlaySession(session);
  loadBookings();
}

// ── Booking details modal ───────────────────────────────────────
const isDetailsOpen = ref(false);
const selectedBooking = ref<BookingDetail | null>(null);
const updatingId = ref<string | null>(null);

function openDetails(booking: BookingDetail) {
  if (booking.session_type === 'open_play') {
    openOpenPlayModal(booking);
    return;
  }
  selectedBooking.value = booking;
  isDetailsOpen.value = true;
}

function onModalConfirm(booking: BookingDetail) {
  isDetailsOpen.value = false;
  handleConfirm(booking);
}

function onModalReject(booking: BookingDetail) {
  isDetailsOpen.value = false;
  openReject(booking);
}

function onModalCancel(booking: BookingDetail) {
  isDetailsOpen.value = false;
  openCancel(booking);
}

async function onModalUpdate({ id, data }: { id: string; data: any }) {
  updatingId.value = id;
  try {
    const updated = await updateBooking(hubId.value, id, data);
    replaceBookingInList(updated);
    toast.add({ title: 'Booking updated successfully', color: 'success' });
    isDetailsOpen.value = false;
  } catch (err: any) {
    const msg =
      err?.data?.message ||
      err?.message ||
      'Conflicting schedules exist or invalid data.';
    toast.add({
      title: 'Failed to update booking',
      description: msg,
      color: 'error'
    });
  } finally {
    updatingId.value = null;
  }
}

// ── Helpers ────────────────────────────────────────────────────

function replaceBookingInList(updated: BookingDetail) {
  const idx = allBookings.value.findIndex((b) => b.id === updated.id);
  if (idx >= 0) allBookings.value[idx] = updated;
}

function customerLabel(b: BookingDetail): string {
  if (b.booked_by_user)
    return `${b.booked_by_user.first_name} ${b.booked_by_user.last_name}`.trim();
  if (b.guest_name) return `${b.guest_name} (guest)`;
  return 'Unknown';
}

function formatDateRange(start: string, end: string): string {
  const s = new Date(start);
  const e = new Date(end);
  const dateStr = s.toLocaleDateString('en-PH', {
    timeZone: 'Asia/Manila',
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  });
  const timeStart = s.toLocaleTimeString('en-PH', {
    timeZone: 'Asia/Manila',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  });
  const timeEnd = e.toLocaleTimeString('en-PH', {
    timeZone: 'Asia/Manila',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  });
  return `${dateStr} · ${timeStart} – ${timeEnd}`;
}

type DisplayStatus = BookingStatus | 'expired';

function effectiveStatus(booking: BookingDetail): DisplayStatus {
  if (booking.status === 'cancelled' && booking.cancelled_by === 'system')
    return 'expired';
  return booking.status;
}

function statusColor(
  status: DisplayStatus
): 'warning' | 'success' | 'error' | 'neutral' | 'primary' {
  switch (status) {
    case 'pending_payment':
      return 'warning';
    case 'payment_sent':
      return 'primary';
    case 'confirmed':
      return 'success';
    case 'cancelled':
      return 'error';
    case 'completed':
    case 'expired':
      return 'neutral';
  }
}

function statusLabel(status: DisplayStatus): string {
  switch (status) {
    case 'pending_payment':
      return 'Pending Payment';
    case 'payment_sent':
      return 'Receipt Sent';
    case 'confirmed':
      return 'Confirmed';
    case 'cancelled':
      return 'Cancelled';
    case 'completed':
      return 'Completed';
    case 'expired':
      return 'Expired';
  }
}

const isCancellable = (status: BookingStatus) =>
  !['cancelled', 'completed'].includes(status);

const columns = [
  { accessorKey: 'customer', header: 'Customer' },
  { accessorKey: 'court', header: 'Court' },
  { accessorKey: 'datetime', header: 'Date & Time' },
  { accessorKey: 'status', header: 'Status' },
  { accessorKey: 'receipt', header: 'Receipt' },
  { id: 'actions', header: '' }
];

const columnPinning = ref({ left: [], right: ['actions'] });

function bookingDropdownItems(booking: BookingDetail) {
  const groups: {
    label: string;
    icon: string;
    color?: 'error';
    loading?: boolean;
    onSelect: () => void;
  }[][] = [];
  if (
    booking.status === 'payment_sent' ||
    booking.status === 'pending_payment'
  ) {
    groups.push([
      {
        label:
          booking.status === 'payment_sent'
            ? 'Confirm Payment'
            : 'Confirm Booking',
        icon: 'i-heroicons-check-circle',
        loading: confirmingId.value === booking.id,
        onSelect: () => handleConfirm(booking)
      },
      {
        label:
          booking.status === 'payment_sent'
            ? 'Reject Receipt'
            : 'Reject Booking',
        icon: 'i-heroicons-x-circle',
        color: 'error' as const,
        onSelect: () => openReject(booking)
      }
    ]);
  }
  if (isCancellable(booking.status)) {
    groups.push([
      {
        label: 'Cancel Booking',
        icon: 'i-heroicons-x-mark',
        color: 'error' as const,
        loading: cancellingId.value === booking.id,
        onSelect: () => openCancel(booking)
      }
    ]);
  }
  return groups;
}
</script>

<template>
  <div class="flex flex-col min-w-0 w-full max-w-full">
    <HubTabNav :tabs="manageTabs" />

    <div class="mx-auto w-full max-w-[1400px] px-4 py-8 md:px-6">
      <!-- Page header -->
      <div class="mb-6 flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-[#0f1728]">Bookings</h1>
          <p class="mt-1 text-sm text-[#64748b]">
            Manage court bookings and walk-in reservations.
          </p>
        </div>
        <UFieldGroup>
          <div class="flex rounded-lg border border-[#dbe4ef] p-0.5">
            <UButton
              size="sm"
              :variant="viewMode === 'calendar' ? 'solid' : 'ghost'"
              :color="viewMode === 'calendar' ? 'primary' : 'neutral'"
              icon="i-heroicons-calendar-days"
              class="rounded-md px-3"
              @click="viewMode = 'calendar'"
            >
              Calendar
            </UButton>
            <UButton
              size="sm"
              :variant="viewMode === 'table' ? 'solid' : 'ghost'"
              :color="viewMode === 'table' ? 'primary' : 'neutral'"
              icon="i-heroicons-table-cells"
              class="rounded-md px-3"
              @click="viewMode = 'table'"
            >
              Table
            </UButton>
          </div>
        </UFieldGroup>
      </div>

      <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3">
          <USelectMenu
            v-model="statusFilter"
            :items="STATUS_OPTIONS"
            multiple
            value-key="value"
            placeholder="All Statuses"
            class="w-48"
          />
          <USelectMenu
            v-model="courtFilter"
            :items="courtFilterOptions"
            multiple
            value-key="value"
            placeholder="All Courts"
            class="w-48"
          />
          <div v-if="viewMode === 'table'" class="flex items-center gap-0.5">
            <button
              type="button"
              class="flex h-8 w-8 items-center justify-center rounded-md transition-colors hover:bg-[#f1f5f9]"
              aria-label="Previous day"
              @click="
                tableDate = new Date(
                  tableDate.getFullYear(),
                  tableDate.getMonth(),
                  tableDate.getDate() - 1
                )
              "
            >
              <UIcon
                name="i-heroicons-chevron-left"
                class="h-5 w-5 text-[#64748b]"
              />
            </button>
            <AppDatePicker
              variant="nav"
              v-model="tableDate"
              :label="tableDateLabel"
              :allow-past="true"
            />
            <button
              type="button"
              class="flex h-8 w-8 items-center justify-center rounded-md transition-colors hover:bg-[#f1f5f9]"
              aria-label="Next day"
              @click="
                tableDate = new Date(
                  tableDate.getFullYear(),
                  tableDate.getMonth(),
                  tableDate.getDate() + 1
                )
              "
            >
              <UIcon
                name="i-heroicons-chevron-right"
                class="h-5 w-5 text-[#64748b]"
              />
            </button>
          </div>
          <UIcon
            v-if="bookingsLoading"
            name="i-heroicons-arrow-path"
            class="h-4 w-4 animate-spin text-[#64748b]"
          />
        </div>
        <UButton
          v-if="viewMode === 'table' && hubCourts.length"
          icon="i-heroicons-plus"
          class="bg-[#004e89] font-semibold hover:bg-[#003d6b]"
          @click="() => openWalkIn()"
        >
          Add Booking
        </UButton>
      </div>

      <!-- No courts empty state -->
      <div
        v-if="courtsLoaded && !courtsLoadFailed && !hubCourts.length"
        class="flex flex-col items-center gap-4 rounded-xl border border-dashed border-[var(--aktiv-border)] bg-white px-6 py-16 text-center"
      >
        <UIcon
          name="i-heroicons-squares-2x2"
          class="h-10 w-10 text-[var(--aktiv-muted)]"
        />
        <p class="text-lg font-semibold text-[var(--aktiv-ink)]">
          No courts added yet
        </p>
        <p class="text-sm text-[var(--aktiv-muted)]">
          Add courts to your hub before managing bookings.
        </p>
        <UButton variant="outline" :to="`/dashboard/hubs/${hubId}/courts`">
          Go to Courts
        </UButton>
      </div>

      <!-- Table View -->
      <div
        v-else-if="viewMode === 'table'"
        class="overflow-x-auto rounded-2xl border border-[#dbe4ef] bg-white"
      >
        <UTable
          v-model:column-pinning="columnPinning"
          :data="filteredBookings"
          :columns="columns"
        >
          <template #empty>
            <div class="py-12 text-center">
              <UIcon
                name="i-heroicons-calendar-days"
                class="mx-auto h-10 w-10 text-[#c8d5e0]"
              />
              <p class="mt-3 text-sm font-semibold text-[#0f1728]">
                No bookings found
              </p>
              <p class="mt-1 text-[#64748b]">Try adjusting your filters.</p>
            </div>
          </template>

          <template #customer-cell="{ row }">
            <div class="space-y-1">
              <p class="text-sm font-medium text-[#0f1728]">
                {{
                  row.original.session_type === 'open_play'
                    ? 'Open Play'
                    : customerLabel(row.original)
                }}
              </p>
              <UBadge
                v-if="row.original.session_type === 'open_play'"
                label="Open Play"
                color="primary"
                variant="subtle"
              />
              <UBadge
                v-else-if="row.original.booking_source === 'owner_added'"
                label="Walk-in"
                color="neutral"
                variant="subtle"
              />
            </div>
          </template>

          <template #court-cell="{ row }">
            <span
              class="block max-w-[160px] truncate text-sm text-[#0f1728]"
              :title="row.original.court?.name"
            >
              {{ row.original.court?.name ?? '—' }}
            </span>
          </template>

          <template #datetime-cell="{ row }">
            <span class="whitespace-nowrap text-sm text-[#64748b]">
              {{
                formatDateRange(row.original.start_time, row.original.end_time)
              }}
            </span>
          </template>

          <template #status-cell="{ row }">
            <div class="space-y-1">
              <UBadge
                :label="statusLabel(effectiveStatus(row.original))"
                :color="statusColor(effectiveStatus(row.original))"
                variant="subtle"
              />
              <p
                v-if="
                  row.original.payment_note &&
                  row.original.status === 'pending_payment'
                "
                class="rounded bg-[#fef9c3] px-1.5 py-0.5 text-[#92400e]"
              >
                {{ row.original.payment_note }}
              </p>
            </div>
          </template>

          <template #receipt-cell="{ row }">
            <a
              v-if="row.original.receipt_image_url"
              :href="row.original.receipt_image_url"
              target="_blank"
              rel="noopener noreferrer"
            >
              <img
                :src="row.original.receipt_image_url"
                alt="Receipt"
                class="h-10 w-10 rounded-md border border-[#dbe4ef] object-cover transition-opacity hover:opacity-75"
              />
            </a>
            <span v-else class="text-sm text-[#c8d5e0]">—</span>
          </template>

          <template #actions-cell="{ row }">
            <UButton
              v-if="
                row.original.session_type === 'open_play' &&
                row.original.open_play_session_id
              "
              size="sm"
              color="primary"
              variant="outline"
              icon="i-heroicons-pencil-square"
              @click="openOpenPlayModal(row.original)"
            >
              Manage
            </UButton>
            <UDropdownMenu
              v-else-if="bookingDropdownItems(row.original).length"
              :items="bookingDropdownItems(row.original)"
            >
              <UButton
                icon="i-heroicons-ellipsis-horizontal"
                color="neutral"
                variant="ghost"
              />
            </UDropdownMenu>
          </template>
        </UTable>
      </div>

      <!-- Calendar View -->
      <div v-else-if="viewMode === 'calendar'" class="min-w-0 overflow-hidden">
        <BookingOwnerGrid
          v-model:selected-date="selectedDate"
          :courts="filteredCourts"
          :bookings="filteredBookings"
          :min-time="gridMinTime"
          :max-time="gridMaxTime"
          :open-play-sessions-map="openPlaySessionsMap"
          :operating-hours="hubData?.operating_hours ?? []"
          @book-slot="openWalkIn"
          @action-confirm="handleConfirm"
          @action-reject="openReject"
          @action-cancel="openCancel"
          @view-booking="openDetails"
          @view-open-play="openOpenPlayModal"
        />
      </div>
    </div>

    <!-- ── Reject modal ──────────────────────────────────────────── -->
    <AppModal
      v-model:open="isRejectOpen"
      title="Reject Receipt"
      cancel-variant="outline"
      confirm="Reject Receipt"
      confirm-color="error"
      :confirm-loading="rejectingId !== null"
      @confirm="submitReject"
    >
      <template #body>
        <p class="mb-3 text-sm text-[#64748b]">
          Provide a reason so the customer knows what to re-upload.
        </p>
        <UTextarea
          v-model="rejectNote"
          placeholder="e.g. Receipt is blurry, wrong amount shown…"
          :rows="4"
          :maxlength="500"
          class="w-full"
        />
        <p v-if="rejectError" class="mt-1.5 text-red-600">
          {{ rejectError }}
        </p>
      </template>
    </AppModal>

    <!-- ── Cancel confirmation modal ────────────────────────────────── -->
    <AppModal
      v-model:open="isCancelOpen"
      title="Cancel Booking"
      cancel="Keep Booking"
      cancel-variant="outline"
      confirm="Yes, Cancel"
      confirm-color="error"
      :confirm-loading="cancellingId !== null"
      @confirm="submitCancel"
    >
      <template #body>
        <p class="text-sm text-[#64748b]">
          Are you sure you want to cancel this booking? The slot will be
          released immediately.
        </p>
        <div
          v-if="cancelTargetBooking"
          class="mt-3 rounded-xl border border-[#dbe4ef] bg-[#f8fafc] p-3 text-sm"
        >
          <p class="font-medium text-[#0f1728]">
            {{ customerLabel(cancelTargetBooking) }}
          </p>
          <p class="mt-0.5 text-[#64748b]">
            {{ cancelTargetBooking.court?.name }} ·
            {{
              formatDateRange(
                cancelTargetBooking.start_time,
                cancelTargetBooking.end_time
              )
            }}
          </p>
        </div>
      </template>
    </AppModal>

    <!-- ── Walk-in / Open Play modal ────────────────────────────────────── -->
    <BookingWalkInModal
      v-model:open="isWalkInOpen"
      :hub-id="hubId"
      :courts="hubCourts"
      :initial-date="calendarSlot?.date"
      :initial-hour="calendarSlot?.hour"
      :initial-court-id="calendarSlot?.courtId"
      :operating-hours="hubData?.operating_hours ?? []"
      @created="onWalkInCreated"
      @openplay:created="onOpenPlayCreated"
    />

    <OpenPlayOwnerModal
      v-if="selectedOpenPlaySessionId"
      v-model:open="isOpenPlayModalOpen"
      :hub-id="hubId"
      :session-id="selectedOpenPlaySessionId"
      :courts="hubCourts"
      :operating-hours="hubData?.operating_hours ?? []"
      @updated="onOpenPlayUpdated"
    />

    <!-- ── Booking Details modal ─────────────────────────────────────── -->
    <BookingDetailsModal
      v-if="
        selectedBooking &&
        !['cancelled', 'completed', 'confirmed'].includes(
          selectedBooking.status
        )
      "
      v-model:open="isDetailsOpen"
      :booking="selectedBooking"
      :courts="hubCourts"
      :confirm-loading="confirmingId === selectedBooking?.id"
      :cancel-loading="cancellingId === selectedBooking?.id"
      :update-loading="updatingId === selectedBooking?.id"
      @action-confirm="onModalConfirm"
      @action-reject="onModalReject"
      @action-cancel="onModalCancel"
      @action-update="onModalUpdate"
    />
    <BookingViewModal
      v-else
      v-model:open="isDetailsOpen"
      :booking="selectedBooking"
    />
  </div>
</template>
