<script setup lang="ts">
import type { BookingDetail } from '~/types/booking';
import type { Court } from '~/types/hub';
import { useHubStore } from '~/stores/hub';

definePageMeta({ layout: 'dashboard', middleware: ['auth', 'owner'] });

useHead({ title: 'Dashboard · Aktiv' });

const hubStore = useHubStore();
const { fetchHubBookings } = useOwnerBookings();
const { fetchCourts } = useHubs();

// ── State ──────────────────────────────────────────────────────────────────────

const loading = ref(true);
const pendingBookings = ref<BookingDetail[]>([]);
const paymentSentBookings = ref<BookingDetail[]>([]);
const todayBookings = ref<BookingDetail[]>([]);

const isHubPickerOpen = ref(false);
const isWalkInModalOpen = ref(false);
const walkInHubId = ref<string | null>(null);
const walkInCourts = ref<Court[]>([]);
const walkInLoading = ref(false);
const walkInInitialDate = ref<string | undefined>(undefined);
const walkInInitialHour = ref<number | undefined>(undefined);

// ── Computed ───────────────────────────────────────────────────────────────────

const activeHubsCount = computed(
  () => hubStore.myHubs.filter((h) => h.is_active).length
);

const todayConfirmedCount = computed(
  () => todayBookings.value.filter((b) => b.status === 'confirmed').length
);

const revenueToday = computed(() => {
  const total = todayBookings.value
    .filter((b) => b.status === 'confirmed')
    .reduce((sum, b) => sum + parseFloat(b.total_price ?? '0'), 0);
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(total);
});

const firstHubId = computed(() => hubStore.myHubs[0]?.id ?? null);

const actionNeededList = computed(() =>
  [...paymentSentBookings.value, ...pendingBookings.value].slice(0, 10)
);

// ── Init ──────────────────────────────────────────────────────────────────────

onMounted(async () => {
  await hubStore.fetchMyHubs();
  if (hubStore.myHubs.length) {
    await loadDashboardData();
  }
  loading.value = false;
});

async function loadDashboardData() {
  const todayStr = new Date().toLocaleDateString('en-CA', {
    timeZone: 'Asia/Manila'
  });

  const results = await Promise.all(
    hubStore.myHubs.map((hub) =>
      Promise.all([
        fetchHubBookings(hub.id, { status: 'pending_payment' }),
        fetchHubBookings(hub.id, { status: 'payment_sent' }),
        fetchHubBookings(hub.id, { date_from: todayStr, date_to: todayStr })
      ])
    )
  );

  const allPending: BookingDetail[] = [];
  const allPaymentSent: BookingDetail[] = [];
  const allToday: BookingDetail[] = [];

  for (const [pending, paymentSent, today] of results) {
    allPending.push(...pending);
    allPaymentSent.push(...paymentSent);
    allToday.push(...today);
  }

  pendingBookings.value = allPending.sort(
    (a, b) =>
      new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
  );

  paymentSentBookings.value = allPaymentSent.sort(
    (a, b) =>
      new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
  );

  todayBookings.value = allToday.sort(
    (a, b) =>
      new Date(a.start_time).getTime() - new Date(b.start_time).getTime()
  );
}

// ── Quick Actions ─────────────────────────────────────────────────────────────

function openWalkInModal() {
  if (!hubStore.myHubs.length) return;

  if (hubStore.myHubs.length > 1) {
    isHubPickerOpen.value = true;
    return;
  }

  selectWalkInHub(String(hubStore.myHubs[0]!.id));
}

function getNextManilaHourSlot(): { date: string; hour: number } {
  const formatter = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Manila',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false
  });

  const parts = formatter.formatToParts(new Date());
  const partValue = (type: string) =>
    Number(parts.find((part) => part.type === type)?.value ?? 0);

  const nextSlot = new Date(
    partValue('year'),
    partValue('month') - 1,
    partValue('day'),
    partValue('hour'),
    partValue('minute'),
    partValue('second')
  );

  nextSlot.setMinutes(0, 0, 0);
  nextSlot.setHours(nextSlot.getHours() + 1);

  const year = nextSlot.getFullYear();
  const month = String(nextSlot.getMonth() + 1).padStart(2, '0');
  const day = String(nextSlot.getDate()).padStart(2, '0');

  return {
    date: `${year}-${month}-${day}`,
    hour: nextSlot.getHours()
  };
}

async function selectWalkInHub(hubId: string) {
  isHubPickerOpen.value = false;
  walkInHubId.value = hubId;
  walkInLoading.value = true;
  try {
    const nextSlot = getNextManilaHourSlot();
    walkInInitialDate.value = nextSlot.date;
    walkInInitialHour.value = nextSlot.hour;
    walkInCourts.value = await fetchCourts(hubId);
    isWalkInModalOpen.value = true;
  } finally {
    walkInLoading.value = false;
  }
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function formatTime(iso: string) {
  return new Date(iso).toLocaleTimeString('en-PH', {
    timeZone: 'Asia/Manila',
    hour: 'numeric',
    minute: '2-digit'
  });
}

function formatDateTime(iso: string) {
  return new Date(iso).toLocaleString('en-PH', {
    timeZone: 'Asia/Manila',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit'
  });
}

function bookerName(booking: BookingDetail) {
  const name =
    `${booking.booked_by_user?.first_name} ${booking.booked_by_user?.last_name}`.trim();

  return name || booking.guest_name || 'Guest';
}

function hubNameForBooking(booking: BookingDetail) {
  const hub = hubStore.myHubs.find((h) => h.id === booking.court?.hub_id);
  return hub?.name ?? '—';
}

function expiresIn(iso: string | null): string | null {
  if (!iso) return null;
  const mins = Math.round((new Date(iso).getTime() - Date.now()) / 60000);
  if (mins <= 0) return 'Expiring';
  if (mins < 60) return `${mins}m left`;
  return `${Math.floor(mins / 60)}h left`;
}

const statusConfig: Record<string, { label: string; color: string }> = {
  pending_payment: {
    label: 'Pending Payment',
    color: 'text-amber-600 bg-amber-50'
  },
  payment_sent: {
    label: 'Receipt Uploaded',
    color: 'text-blue-600 bg-blue-50'
  },
  confirmed: { label: 'Confirmed', color: 'text-green-700 bg-green-50' },
  cancelled: { label: 'Cancelled', color: 'text-red-600 bg-red-50' },
  completed: { label: 'Completed', color: 'text-[#64748b] bg-[#f0f4f8]' }
};
</script>

<template>
  <div>
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-[#0f1728]">Overview</h1>
      <p class="mt-1 text-sm text-[#64748b]">
        Welcome back. Here's what's happening today.
      </p>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="flex items-center gap-2 text-[#64748b]">
      <UIcon name="i-heroicons-arrow-path" class="h-5 w-5 animate-spin" />
      <span class="text-sm">Loading dashboard…</span>
    </div>

    <template v-else>
      <!-- ── Stat Cards ──────────────────────────────────────────────────────── -->
      <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Needs Review -->
        <NuxtLink
          :to="
            firstHubId
              ? `/dashboard/hubs/${firstHubId}/bookings`
              : '/dashboard/hubs'
          "
          class="flex items-center gap-4 rounded-2xl border border-[#dbe4ef] bg-white p-5 transition hover:shadow-md"
        >
          <div
            class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-blue-50"
          >
            <UIcon name="i-heroicons-eye" class="h-6 w-6 text-blue-500" />
          </div>
          <div>
            <p class="text-sm text-[#64748b]">Needs Review</p>
            <p class="text-2xl font-bold text-[#0f1728]">
              {{ paymentSentBookings.length }}
            </p>
          </div>
        </NuxtLink>

        <!-- Pending Payments -->
        <NuxtLink
          :to="
            firstHubId
              ? `/dashboard/hubs/${firstHubId}/bookings`
              : '/dashboard/hubs'
          "
          class="flex items-center gap-4 rounded-2xl border border-[#dbe4ef] bg-white p-5 transition hover:shadow-md"
        >
          <div
            class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-amber-50"
          >
            <UIcon name="i-heroicons-clock" class="h-6 w-6 text-amber-500" />
          </div>
          <div>
            <p class="text-sm text-[#64748b]">Pending Payments</p>
            <p class="text-2xl font-bold text-[#0f1728]">
              {{ pendingBookings.length }}
            </p>
          </div>
        </NuxtLink>

        <!-- Today's Confirmed -->
        <NuxtLink
          :to="
            firstHubId
              ? `/dashboard/hubs/${firstHubId}/bookings`
              : '/dashboard/hubs'
          "
          class="flex items-center gap-4 rounded-2xl border border-[#dbe4ef] bg-white p-5 transition hover:shadow-md"
        >
          <div
            class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-green-50"
          >
            <UIcon
              name="i-heroicons-calendar-days"
              class="h-6 w-6 text-green-600"
            />
          </div>
          <div>
            <p class="text-sm text-[#64748b]">Today's Confirmed</p>
            <p class="text-2xl font-bold text-[#0f1728]">
              {{ todayConfirmedCount }}
            </p>
          </div>
        </NuxtLink>

        <!-- Revenue Today -->
        <div
          class="flex items-center gap-4 rounded-2xl border border-[#dbe4ef] bg-white p-5"
        >
          <div
            class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-[#e8f0f8]"
          >
            <UIcon
              name="i-heroicons-banknotes"
              class="h-6 w-6 text-[#004e89]"
            />
          </div>
          <div>
            <p class="text-sm text-[#64748b]">Revenue Today</p>
            <p class="text-2xl font-bold text-[#0f1728]">
              {{ revenueToday }}
            </p>
          </div>
        </div>
      </div>

      <!-- ── Quick Actions ───────────────────────────────────────────────────── -->
      <div class="mb-6 flex items-center justify-end gap-3">
        <UButton
          variant="solid"
          color="primary"
          icon="i-heroicons-plus"
          :loading="walkInLoading"
          @click="openWalkInModal"
        >
          Add Walk-in / Open Play
        </UButton>
      </div>

      <div class="grid gap-6 lg:grid-cols-2">
        <!-- ── Action Needed ────────────────────────────────────────────────── -->
        <div class="rounded-2xl border border-[#dbe4ef] bg-white">
          <div
            class="flex items-center justify-between border-b border-[#dbe4ef] px-5 py-4"
          >
            <h2 class="text-sm font-semibold text-[#0f1728]">Action Needed</h2>
            <NuxtLink
              :to="
                firstHubId
                  ? `/dashboard/hubs/${firstHubId}/bookings`
                  : '/dashboard/hubs'
              "
              class="text-xs font-medium text-[#004e89] hover:underline"
            >
              View all
            </NuxtLink>
          </div>

          <!-- Empty state -->
          <div
            v-if="!actionNeededList.length"
            class="flex flex-col items-center justify-center px-5 py-10 text-center"
          >
            <UIcon
              name="i-heroicons-check-circle"
              class="h-8 w-8 text-green-400"
            />
            <p class="mt-2 text-sm text-[#64748b]">Nothing needs attention</p>
          </div>

          <!-- List -->
          <ul v-else class="divide-y divide-[#f0f4f8]">
            <li
              v-for="booking in actionNeededList"
              :key="booking.id"
              class="flex cursor-pointer items-start justify-between gap-3 px-5 py-3.5 hover:bg-[#f8fafc]"
              @click="
                navigateTo({
                  path: `/dashboard/hubs/${booking.court?.hub_id}/bookings`,
                  query: { bookingId: booking.id }
                })
              "
            >
              <div class="min-w-0">
                <p class="truncate text-sm font-medium text-[#0f1728]">
                  {{ bookerName(booking) }}
                </p>
                <p class="mt-0.5 truncate text-xs text-[#64748b]">
                  {{ hubNameForBooking(booking) }} · {{ booking.court?.name }}
                </p>
                <p class="mt-0.5 text-xs text-[#64748b]">
                  {{ formatDateTime(booking.start_time) }}
                </p>
                <span
                  v-if="
                    booking.status === 'pending_payment' &&
                    expiresIn(booking.expires_at)
                  "
                  class="mt-1 inline-block rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-500"
                >
                  {{ expiresIn(booking.expires_at) }}
                </span>
              </div>
              <span
                class="flex-shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                :class="statusConfig[booking.status]?.color"
              >
                {{ statusConfig[booking.status]?.label }}
              </span>
            </li>
          </ul>
        </div>

        <!-- ── Today's Schedule ────────────────────────────────────────────── -->
        <div class="rounded-2xl border border-[#dbe4ef] bg-white">
          <div
            class="flex items-center justify-between border-b border-[#dbe4ef] px-5 py-4"
          >
            <h2 class="text-sm font-semibold text-[#0f1728]">
              Today's Schedule
            </h2>
            <NuxtLink
              :to="
                firstHubId
                  ? `/dashboard/hubs/${firstHubId}/bookings`
                  : '/dashboard/hubs'
              "
              class="text-xs font-medium text-[#004e89] hover:underline"
            >
              View all
            </NuxtLink>
          </div>

          <!-- Empty state -->
          <div
            v-if="!todayBookings.length"
            class="flex flex-col items-center justify-center px-5 py-10 text-center"
          >
            <UIcon name="i-heroicons-calendar" class="h-8 w-8 text-[#c8d5e0]" />
            <p class="mt-2 text-sm text-[#64748b]">
              No bookings scheduled for today
            </p>
          </div>

          <!-- List -->
          <ul v-else class="divide-y divide-[#f0f4f8]">
            <li
              v-for="booking in todayBookings"
              :key="booking.id"
              class="flex cursor-pointer items-start justify-between gap-3 px-5 py-3.5 hover:bg-[#f8fafc]"
              @click="
                navigateTo({
                  path: `/dashboard/hubs/${booking.court?.hub_id}/bookings`,
                  query: { bookingId: booking.id }
                })
              "
            >
              <div class="min-w-0">
                <p class="truncate text-sm font-medium text-[#0f1728]">
                  {{ bookerName(booking) }}
                </p>
                <p class="mt-0.5 truncate text-xs text-[#64748b]">
                  {{ hubNameForBooking(booking) }} · {{ booking.court?.name }}
                </p>
                <p class="mt-0.5 text-xs text-[#64748b]">
                  {{ formatTime(booking.start_time) }} –
                  {{ formatTime(booking.end_time) }}
                </p>
              </div>
              <span
                class="flex-shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                :class="statusConfig[booking.status]?.color"
              >
                {{ statusConfig[booking.status]?.label }}
              </span>
            </li>
          </ul>
        </div>
      </div>
    </template>

    <!-- Hub picker (multi-hub owners only) -->
    <AppModal v-model:open="isHubPickerOpen" title="Select a Hub">
      <template #body>
        <ul class="divide-y divide-[#f0f4f8]">
          <li
            v-for="hub in hubStore.myHubs"
            :key="hub.id"
            class="flex cursor-pointer items-center gap-3 px-4 py-3 hover:bg-[#f8fafc]"
            @click="selectWalkInHub(String(hub.id))"
          >
            <div
              class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-[#e8f0f8]"
            >
              <UIcon
                name="i-heroicons-building-office-2"
                class="h-5 w-5 text-[#004e89]"
              />
            </div>
            <div class="min-w-0">
              <p class="truncate text-sm font-medium text-[#0f1728]">
                {{ hub.name }}
              </p>
              <p class="truncate text-xs text-[#64748b]">
                {{ hub.city }} · {{ hub.courts_count }}
                {{ hub.courts_count === 1 ? 'court' : 'courts' }}
              </p>
            </div>
            <UIcon
              name="i-heroicons-chevron-right"
              class="ml-auto h-4 w-4 flex-shrink-0 text-[#94a3b8]"
            />
          </li>
        </ul>
      </template>
    </AppModal>

    <BookingWalkInModal
      v-if="walkInHubId"
      v-model:open="isWalkInModalOpen"
      :hub-id="walkInHubId"
      :courts="walkInCourts"
      :initial-date="walkInInitialDate"
      :initial-hour="walkInInitialHour"
      @created="loadDashboardData"
      @openplay:created="loadDashboardData"
    />
  </div>
</template>
