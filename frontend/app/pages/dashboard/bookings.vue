<script setup lang="ts">
import type { Hub, Court } from '~/types/hub';
import type { BookingDetail, BookingStatus } from '~/types/booking';
import { useHubStore } from '~/stores/hub';
import { useHubs } from '~/composables/useHubs';
import { useOwnerBookings } from '~/composables/useOwnerBookings';

definePageMeta({ layout: 'dashboard', middleware: 'auth' });

const hubStore = useHubStore();
const { fetchCourts } = useHubs();
const {
  fetchHubBookings,
  confirmBooking,
  rejectBooking,
  cancelBooking,
  createWalkIn,
  updateBooking,
  searchUsers
} = useOwnerBookings();
const toast = useToast();
const route = useRoute();

// ── View mode ─────────────────────────────────────────────────
const viewMode = ref<'table' | 'calendar'>('calendar');
const selectedDate = ref(new Date());
const calendarSlot = ref<{
  date: string;
  hour: number;
  courtId?: number;
} | null>(null);

// ── Hub selector ──────────────────────────────────────────────

const selectedHubId = ref<number | undefined>(undefined);

const hubOptions = computed(() =>
  hubStore.myHubs.map((h: Hub) => ({ label: h.name, value: h.id }))
);

// ── Courts for this hub ─────────────────────────────────────

const hubCourts = ref<Court[]>([]);

async function loadCourts() {
  if (!selectedHubId.value) return;
  try {
    hubCourts.value = await fetchCourts(selectedHubId.value);
  } catch {
    hubCourts.value = [];
  }
}

// ── Bookings ──────────────────────────────────────────────────

const bookingsLoading = ref(false);
const allBookings = ref<BookingDetail[]>([]);

function formatDateString(date: Date): string {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

async function loadBookings() {
  if (!selectedHubId.value) return;
  bookingsLoading.value = true;
  try {
    const dateStr = formatDateString(selectedDate.value);
    allBookings.value = await fetchHubBookings(selectedHubId.value, {
      date_from: dateStr,
      date_to: dateStr
    });
  } catch {
    toast.add({ title: 'Failed to load bookings', color: 'error' });
  } finally {
    bookingsLoading.value = false;
  }
}

// ── Filters ───────────────────────────────────────────────────

const statusFilter = ref<BookingStatus[]>([]);
const courtFilter = ref<number[]>([]);

const STATUS_OPTIONS: { label: string; value: BookingStatus }[] = [
  { label: 'Pending Payment', value: 'pending_payment' },
  { label: 'Receipt Sent', value: 'payment_sent' },
  { label: 'Confirmed', value: 'confirmed' },
  { label: 'Cancelled', value: 'cancelled' },
  { label: 'Completed', value: 'completed' }
];

const courtFilterOptions = computed(() =>
  hubCourts.value.map((c) => ({ label: c.name, value: c.id as number }))
);

const filteredBookings = computed(() => {
  let list = allBookings.value;
  if (statusFilter.value.length > 0)
    list = list.filter((b) => statusFilter.value.includes(b.status));
  if (courtFilter.value.length > 0)
    list = list.filter((b) => courtFilter.value.includes(b.court_id));
  return list;
});

const filteredCourts = computed(() => {
  if (courtFilter.value.length === 0) return hubCourts.value;
  return hubCourts.value.filter((c) => courtFilter.value.includes(c.id));
});

// ── Init ──────────────────────────────────────────────────────

onMounted(async () => {
  await hubStore.fetchMyHubs();
  if (hubStore.myHubs.length) {
    const qRaw = Array.isArray(route.query.hubId)
      ? route.query.hubId[0]
      : route.query.hubId;
    const qId = Number(qRaw);
    const match = hubStore.myHubs.find((h: Hub) => h.id === qId);
    selectedHubId.value = match ? qId : hubStore.myHubs[0]?.id;
    await Promise.all([loadBookings(), loadCourts()]);
  }
});

watch(selectedHubId, async () => {
  allBookings.value = [];
  hubCourts.value = [];
  await Promise.all([loadBookings(), loadCourts()]);
});

watch(selectedDate, async () => {
  if (viewMode.value === 'calendar') {
    await loadBookings();
  }
});

// ── Confirm ───────────────────────────────────────────────────

const confirmingId = ref<number | null>(null);

async function handleConfirm(booking: BookingDetail) {
  if (!selectedHubId.value) return;
  confirmingId.value = booking.id;
  try {
    const updated = await confirmBooking(selectedHubId.value, booking.id);
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
const rejectingId = ref<number | null>(null);

function openReject(booking: BookingDetail) {
  rejectTargetBooking.value = booking;
  rejectNote.value = '';
  rejectError.value = '';
  isRejectOpen.value = true;
}

async function submitReject() {
  if (!selectedHubId.value || !rejectTargetBooking.value) return;
  if (!rejectNote.value.trim()) {
    rejectError.value = 'Please provide a rejection reason.';
    return;
  }
  rejectingId.value = rejectTargetBooking.value.id;
  try {
    const updated = await rejectBooking(
      selectedHubId.value,
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
const cancellingId = ref<number | null>(null);

function openCancel(booking: BookingDetail) {
  cancelTargetBooking.value = booking;
  isCancelOpen.value = true;
}

async function submitCancel() {
  if (!selectedHubId.value || !cancelTargetBooking.value) return;
  cancellingId.value = cancelTargetBooking.value.id;
  try {
    const updated = await cancelBooking(
      selectedHubId.value,
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

// ── Booking details modal ───────────────────────────────────────

const isDetailsOpen = ref(false);
const selectedBooking = ref<BookingDetail | null>(null);
const updatingId = ref<number | null>(null);

function openDetails(booking: BookingDetail) {
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

async function onModalUpdate({ id, data }: { id: number; data: any }) {
  if (!selectedHubId.value) return;
  updatingId.value = id;
  try {
    const updated = await updateBooking(selectedHubId.value, id, data);
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
  if (b.booked_by_user) return b.booked_by_user.name;
  if (b.guest_name) return `${b.guest_name} (guest)`;
  return 'Unknown';
}

function formatDateTime(iso: string): string {
  return new Date(iso).toLocaleString('en-PH', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  });
}

function formatDateRange(start: string, end: string): string {
  const s = new Date(start);
  const e = new Date(end);
  const dateStr = s.toLocaleDateString('en-PH', {
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  });
  const timeStart = s.toLocaleTimeString('en-PH', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  });
  const timeEnd = e.toLocaleTimeString('en-PH', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  });
  return `${dateStr} · ${timeStart} – ${timeEnd}`;
}

function statusColor(
  status: BookingStatus
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
      return 'neutral';
  }
}

function statusLabel(status: BookingStatus): string {
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
  }
}

const isCancellable = (status: BookingStatus) =>
  !['cancelled', 'completed'].includes(status);

const columns = [
  { accessorKey: 'customer', header: 'Customer' },
  { accessorKey: 'court', header: 'Court' },
  { accessorKey: 'sport', header: 'Sport' },
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
  if (booking.status === 'payment_sent') {
    groups.push([
      {
        label: 'Confirm Payment',
        icon: 'i-heroicons-check-circle',
        loading: confirmingId.value === booking.id,
        onSelect: () => handleConfirm(booking)
      },
      {
        label: 'Reject Receipt',
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

    <!-- Hubs loading -->
    <div
      v-if="!hubStore.initialized || hubStore.loading"
      class="flex items-center gap-2 text-[#64748b]"
    >
      <UIcon name="i-heroicons-arrow-path" class="h-5 w-5 animate-spin" />
      <span class="text-sm">Loading…</span>
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
        Create a hub first to manage its bookings.
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

      <!-- Bookings loading -->
      <div
        v-if="bookingsLoading"
        class="flex items-center gap-2 text-[#64748b]"
      >
        <UIcon name="i-heroicons-arrow-path" class="h-5 w-5 animate-spin" />
        <span class="text-sm">Loading bookings…</span>
      </div>

      <template v-else>
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
          </div>
          <UButton
            v-if="viewMode === 'table' && selectedHubId && hubCourts.length"
            icon="i-heroicons-plus"
            class="bg-[#004e89] font-semibold hover:bg-[#003d6b]"
            @click="() => openWalkIn()"
          >
            Add Walk-in
          </UButton>
        </div>

        <!-- Table View -->
        <div
          v-if="viewMode === 'table'"
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
                  {{ customerLabel(row.original) }}
                </p>
                <UBadge
                  v-if="row.original.booking_source === 'owner_added'"
                  label="Walk-in"
                  color="neutral"
                  variant="subtle"
                />
              </div>
            </template>

            <template #court-cell="{ row }">
              <span class="text-sm text-[#0f1728]">
                {{ row.original.court?.name ?? '—' }}
              </span>
            </template>

            <template #sport-cell="{ row }">
              <span class="text-sm capitalize text-[#0f1728]">
                {{ row.original.sport }}
              </span>
            </template>

            <template #datetime-cell="{ row }">
              <span class="whitespace-nowrap text-sm text-[#64748b]">
                {{
                  formatDateRange(
                    row.original.start_time,
                    row.original.end_time
                  )
                }}
              </span>
            </template>

            <template #status-cell="{ row }">
              <div class="space-y-1">
                <UBadge
                  :label="statusLabel(row.original.status)"
                  :color="statusColor(row.original.status)"
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
              <UDropdownMenu
                v-if="bookingDropdownItems(row.original).length"
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
        <div v-else class="min-w-0 overflow-hidden">
          <BookingOwnerGrid
            v-model:selected-date="selectedDate"
            :courts="filteredCourts"
            :bookings="filteredBookings"
            @book-slot="openWalkIn"
            @action-confirm="handleConfirm"
            @action-reject="openReject"
            @action-cancel="openCancel"
            @view-booking="openDetails"
          />
        </div>
      </template>
    </template>
    <!-- ── Reject modal ──────────────────────────────────────────── -->
    <UModal v-model:open="isRejectOpen" title="Reject Receipt">
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
      <template #footer>
        <div class="flex justify-end gap-2">
          <UButton
            color="neutral"
            variant="outline"
            @click="isRejectOpen = false"
          >
            Cancel
          </UButton>
          <UButton
            color="error"
            :loading="rejectingId !== null"
            @click="submitReject"
          >
            Reject Receipt
          </UButton>
        </div>
      </template>
    </UModal>

    <!-- ── Cancel confirmation modal ────────────────────────────────── -->
    <UModal v-model:open="isCancelOpen" title="Cancel Booking">
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
      <template #footer>
        <div class="flex justify-end gap-2">
          <UButton
            color="neutral"
            variant="outline"
            @click="isCancelOpen = false"
          >
            Keep Booking
          </UButton>
          <UButton
            color="error"
            :loading="cancellingId !== null"
            @click="submitCancel"
          >
            Yes, Cancel
          </UButton>
        </div>
      </template>
    </UModal>

    <!-- ── Walk-in modal ─────────────────────────────────────────────── -->
    <BookingWalkInModal
      v-model:open="isWalkInOpen"
      :hub-id="selectedHubId"
      :courts="hubCourts"
      :initial-date="calendarSlot?.date"
      :initial-hour="calendarSlot?.hour"
      :initial-court-id="calendarSlot?.courtId"
      @created="onWalkInCreated"
    />

    <!-- ── Booking Details modal ─────────────────────────────────────── -->
    <BookingDetailsModal
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
  </div>
</template>
