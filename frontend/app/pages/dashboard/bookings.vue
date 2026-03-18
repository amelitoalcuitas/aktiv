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
  searchUsers
} = useOwnerBookings();
const toast = useToast();
const route = useRoute();

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

async function loadBookings() {
  if (!selectedHubId.value) return;
  bookingsLoading.value = true;
  try {
    allBookings.value = await fetchHubBookings(selectedHubId.value);
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
const walkInLoading = ref(false);

const walkInForm = reactive({
  courtId: null as number | null,
  sport: '',
  date: '',
  startHour: 8,
  duration: 1,
  customerMode: 'guest' as 'registered' | 'guest',
  bookedBy: null as number | null,
  guestName: '',
  guestPhone: ''
});

const walkInErrors = reactive({
  court: '',
  sport: '',
  date: '',
  customer: ''
});

// User search
const userSearchQuery = ref('');
const userSearchResults = ref<
  {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    avatar_url: string | null;
  }[]
>([]);
const userSearchLoading = ref(false);
const selectedUser = ref<{
  id: number;
  name: string;
  email: string;
} | null>(null);

let searchDebounce: ReturnType<typeof setTimeout>;
watch(userSearchQuery, (q) => {
  clearTimeout(searchDebounce);
  if (!q.trim()) {
    userSearchResults.value = [];
    return;
  }
  searchDebounce = setTimeout(async () => {
    userSearchLoading.value = true;
    try {
      userSearchResults.value = await searchUsers(q.trim());
    } catch {
      userSearchResults.value = [];
    } finally {
      userSearchLoading.value = false;
    }
  }, 350);
});

function selectUser(user: {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  avatar_url: string | null;
}) {
  selectedUser.value = { id: user.id, name: user.name, email: user.email };
  walkInForm.bookedBy = user.id;
  userSearchResults.value = [];
  userSearchQuery.value = '';
}

function clearSelectedUser() {
  selectedUser.value = null;
  walkInForm.bookedBy = null;
}

const walkInCourtOptions = computed(() =>
  hubCourts.value.map((c) => ({ label: c.name, value: c.id }))
);

const walkInSelectedCourt = computed(() =>
  hubCourts.value.find((c) => c.id === walkInForm.courtId)
);

const walkInSportOptions = computed(() =>
  (walkInSelectedCourt.value?.sports ?? []).map((s) => ({
    label: s.charAt(0).toUpperCase() + s.slice(1),
    value: s
  }))
);

watch(
  () => walkInForm.courtId,
  () => {
    walkInForm.sport = walkInSportOptions.value[0]?.value ?? '';
  }
);

const todayStr = computed(() => new Date().toISOString().slice(0, 10));

const startTimeHourOptions = Array.from({ length: 18 }, (_, i) => {
  const h = i + 6;
  const label =
    h === 12 ? '12:00 PM' : h < 12 ? `${h}:00 AM` : `${h - 12}:00 PM`;
  return { label, value: h };
});

const durationOptions = Array.from({ length: 8 }, (_, i) => ({
  label: `${i + 1} hour${i > 0 ? 's' : ''}`,
  value: i + 1
}));

function clearWalkInErrors() {
  walkInErrors.court = '';
  walkInErrors.sport = '';
  walkInErrors.date = '';
  walkInErrors.customer = '';
}

function openWalkIn() {
  clearWalkInErrors();
  walkInForm.courtId = hubCourts.value[0]?.id ?? null;
  walkInForm.sport = '';
  walkInForm.date = new Date().toISOString().slice(0, 10);
  walkInForm.startHour = 8;
  walkInForm.duration = 1;
  walkInForm.customerMode = 'guest';
  walkInForm.bookedBy = null;
  walkInForm.guestName = '';
  walkInForm.guestPhone = '';
  selectedUser.value = null;
  userSearchQuery.value = '';
  userSearchResults.value = [];
  isWalkInOpen.value = true;
}

async function submitWalkIn() {
  clearWalkInErrors();
  let valid = true;

  if (!walkInForm.courtId) {
    walkInErrors.court = 'Select a court.';
    valid = false;
  }
  if (!walkInForm.sport) {
    walkInErrors.sport = 'Select a sport.';
    valid = false;
  }
  if (!walkInForm.date) {
    walkInErrors.date = 'Select a date.';
    valid = false;
  }
  if (walkInForm.customerMode === 'registered' && !walkInForm.bookedBy) {
    walkInErrors.customer = 'Search and select a registered user.';
    valid = false;
  }
  if (walkInForm.customerMode === 'guest' && !walkInForm.guestName.trim()) {
    walkInErrors.customer = 'Guest name is required.';
    valid = false;
  }

  if (!valid) return;

  const [y, mo, day] = walkInForm.date.split('-').map(Number);
  const startDt = new Date(y!, mo! - 1, day!);
  startDt.setHours(walkInForm.startHour, 0, 0, 0);
  const endDt = new Date(startDt.getTime() + walkInForm.duration * 3_600_000);

  walkInLoading.value = true;
  try {
    const booking = await createWalkIn(
      selectedHubId.value!,
      walkInForm.courtId!,
      {
        court_id: walkInForm.courtId!,
        sport: walkInForm.sport,
        start_time: startDt.toISOString(),
        end_time: endDt.toISOString(),
        session_type: 'private',
        booked_by:
          walkInForm.customerMode === 'registered' ? walkInForm.bookedBy : null,
        guest_name:
          walkInForm.customerMode === 'guest'
            ? walkInForm.guestName.trim() || null
            : null,
        guest_phone:
          walkInForm.customerMode === 'guest'
            ? walkInForm.guestPhone.trim() || null
            : null
      }
    );
    allBookings.value.unshift(booking);
    isWalkInOpen.value = false;
    toast.add({ title: 'Walk-in booking created', color: 'success' });
  } catch (err: unknown) {
    const msg =
      (err as { data?: { message?: string } })?.data?.message ??
      'Failed to create walk-in booking.';
    if (msg.includes('already booked')) {
      walkInErrors.date = msg;
    } else {
      toast.add({ title: msg, color: 'error' });
    }
  } finally {
    walkInLoading.value = false;
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
  { id: 'actions', header: '' },
];

function bookingDropdownItems(booking: BookingDetail) {
  const groups: { label: string; icon: string; color?: 'error'; loading?: boolean; onSelect: () => void }[][] = [];
  if (booking.status === 'payment_sent') {
    groups.push([
      {
        label: 'Confirm Payment',
        icon: 'i-heroicons-check-circle',
        loading: confirmingId.value === booking.id,
        onSelect: () => handleConfirm(booking),
      },
      {
        label: 'Reject Receipt',
        icon: 'i-heroicons-x-circle',
        color: 'error' as const,
        onSelect: () => openReject(booking),
      },
    ]);
  }
  if (isCancellable(booking.status)) {
    groups.push([
      {
        label: 'Cancel Booking',
        icon: 'i-heroicons-x-mark',
        color: 'error' as const,
        loading: cancellingId.value === booking.id,
        onSelect: () => openCancel(booking),
      },
    ]);
  }
  return groups;
}
</script>

<template>
  <div>
    <!-- Page header -->
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-[#0f1728]">Bookings</h1>
        <p class="mt-1 text-sm text-[#64748b]">
          Manage court bookings and walk-in reservations.
        </p>
      </div>
      <UButton
        v-if="selectedHubId && hubCourts.length"
        icon="i-heroicons-plus"
        class="bg-[#004e89] font-semibold hover:bg-[#003d6b]"
        @click="openWalkIn"
      >
        Add Walk-in
      </UButton>
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
        <!-- Filters -->
        <div class="mb-4 flex flex-wrap items-center gap-3">
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

        <!-- Table -->
        <div class="overflow-x-auto rounded-2xl border border-[#dbe4ef] bg-white">
          <UTable :data="filteredBookings" :columns="columns">
            <template #empty>
              <div class="py-12 text-center">
                <UIcon
                  name="i-heroicons-calendar-days"
                  class="mx-auto h-10 w-10 text-[#c8d5e0]"
                />
                <p class="mt-3 text-sm font-semibold text-[#0f1728]">
                  No bookings found
                </p>
                <p class="mt-1 text-xs text-[#64748b]">
                  Try adjusting your filters.
                </p>
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
                  size="xs"
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
                {{ formatDateRange(row.original.start_time, row.original.end_time) }}
              </span>
            </template>

            <template #status-cell="{ row }">
              <div class="space-y-1">
                <UBadge
                  :label="statusLabel(row.original.status)"
                  :color="statusColor(row.original.status)"
                  variant="subtle"
                  size="sm"
                />
                <p
                  v-if="row.original.payment_note && row.original.status === 'pending_payment'"
                  class="rounded bg-[#fef9c3] px-1.5 py-0.5 text-xs text-[#92400e]"
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
                  size="xs"
                />
              </UDropdownMenu>
            </template>
          </UTable>
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
        <p v-if="rejectError" class="mt-1.5 text-xs text-red-600">
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
    <UModal
      v-model:open="isWalkInOpen"
      title="Add Walk-in Booking"
      :ui="{ width: 'sm:max-w-xl' }"
    >
      <template #body>
        <div class="space-y-4">
          <!-- Court -->
          <div>
            <label class="mb-1 block text-sm font-medium text-[#0f1728]"
              >Court</label
            >
            <USelect
              v-model="walkInForm.courtId"
              :items="walkInCourtOptions"
              class="w-full"
            />
            <p v-if="walkInErrors.court" class="mt-1 text-xs text-red-600">
              {{ walkInErrors.court }}
            </p>
          </div>

          <!-- Sport -->
          <div>
            <label class="mb-1 block text-sm font-medium text-[#0f1728]"
              >Sport</label
            >
            <USelect
              v-model="walkInForm.sport"
              :items="walkInSportOptions"
              :disabled="!walkInForm.courtId"
              class="w-full"
            />
            <p v-if="walkInErrors.sport" class="mt-1 text-xs text-red-600">
              {{ walkInErrors.sport }}
            </p>
          </div>

          <!-- Date + time -->
          <div class="grid grid-cols-3 gap-3">
            <div class="col-span-1">
              <label class="mb-1 block text-sm font-medium text-[#0f1728]"
                >Date</label
              >
              <UInput
                v-model="walkInForm.date"
                type="date"
                :min="todayStr"
                class="w-full"
              />
              <p v-if="walkInErrors.date" class="mt-1 text-xs text-red-600">
                {{ walkInErrors.date }}
              </p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-[#0f1728]"
                >Start</label
              >
              <USelect
                v-model="walkInForm.startHour"
                :items="startTimeHourOptions"
                class="w-full"
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-[#0f1728]"
                >Duration</label
              >
              <USelect
                v-model="walkInForm.duration"
                :items="durationOptions"
                class="w-full"
              />
            </div>
          </div>

          <!-- Customer -->
          <div>
            <label class="mb-1.5 block text-sm font-medium text-[#0f1728]"
              >Customer</label
            >
            <div class="mb-2 flex gap-2">
              <UButton
                size="sm"
                :variant="
                  walkInForm.customerMode === 'guest' ? 'solid' : 'outline'
                "
                :color="
                  walkInForm.customerMode === 'guest' ? 'primary' : 'neutral'
                "
                @click="walkInForm.customerMode = 'guest'"
              >
                Guest
              </UButton>
              <UButton
                size="sm"
                :variant="
                  walkInForm.customerMode === 'registered' ? 'solid' : 'outline'
                "
                :color="
                  walkInForm.customerMode === 'registered'
                    ? 'primary'
                    : 'neutral'
                "
                @click="walkInForm.customerMode = 'registered'"
              >
                Registered User
              </UButton>
            </div>

            <!-- Guest fields -->
            <template v-if="walkInForm.customerMode === 'guest'">
              <UInput
                v-model="walkInForm.guestName"
                placeholder="Full name"
                class="w-full"
              />
              <UInput
                v-model="walkInForm.guestPhone"
                placeholder="Phone number (optional)"
                class="mt-2 w-full"
              />
            </template>

            <!-- Registered user search -->
            <template v-else>
              <div
                v-if="selectedUser"
                class="flex items-center justify-between rounded-xl border border-[#dbe4ef] bg-[#f8fafc] px-3 py-2"
              >
                <div>
                  <p class="text-sm font-medium text-[#0f1728]">
                    {{ selectedUser.name }}
                  </p>
                  <p class="text-xs text-[#64748b]">{{ selectedUser.email }}</p>
                </div>
                <UButton
                  icon="i-heroicons-x-mark"
                  color="neutral"
                  variant="ghost"
                  size="xs"
                  @click="clearSelectedUser"
                />
              </div>
              <div v-else class="relative">
                <UInput
                  v-model="userSearchQuery"
                  placeholder="Search by name, email, or phone…"
                  :loading="userSearchLoading"
                  class="w-full"
                  icon="i-heroicons-magnifying-glass"
                />
                <!-- Results dropdown -->
                <div
                  v-if="userSearchResults.length"
                  class="absolute z-50 mt-1 w-full overflow-hidden rounded-xl border border-[#dbe4ef] bg-white shadow-lg"
                >
                  <button
                    v-for="u in userSearchResults"
                    :key="u.id"
                    class="w-full px-3 py-2 text-left text-sm hover:bg-[#f0f4f8] transition-colors"
                    type="button"
                    @click="selectUser(u)"
                  >
                    <span class="font-medium text-[#0f1728]">{{ u.name }}</span>
                    <span class="ml-2 text-xs text-[#64748b]">{{
                      u.email
                    }}</span>
                  </button>
                </div>
              </div>
            </template>

            <p v-if="walkInErrors.customer" class="mt-1 text-xs text-red-600">
              {{ walkInErrors.customer }}
            </p>
          </div>
        </div>
      </template>
      <template #footer>
        <div class="flex justify-end gap-2">
          <UButton
            color="neutral"
            variant="outline"
            @click="isWalkInOpen = false"
          >
            Cancel
          </UButton>
          <UButton
            class="bg-[#004e89] hover:bg-[#003d6b]"
            :loading="walkInLoading"
            @click="submitWalkIn"
          >
            Confirm Walk-in
          </UButton>
        </div>
      </template>
    </UModal>
  </div>
</template>
