<script setup lang="ts">
import type { BookingDetail } from '~/types/booking';
import { useHubStore } from '~/stores/hub';

definePageMeta({ layout: 'dashboard', middleware: ['auth', 'owner'] });

useHead({ title: 'Dashboard · Aktiv' });

const hubStore = useHubStore();
const { fetchHubBookings } = useOwnerBookings();

// ── State ──────────────────────────────────────────────────────────────────────

const loading = ref(true);
const pendingBookings = ref<BookingDetail[]>([]);
const todayBookings = ref<BookingDetail[]>([]);
const isVerifyModalOpen = ref(false);

// ── Computed ───────────────────────────────────────────────────────────────────

const activeHubsCount = computed(
  () => hubStore.myHubs.filter((h) => h.is_active).length
);

const todayConfirmedCount = computed(
  () => todayBookings.value.filter((b) => b.status === 'confirmed').length
);

const verifyHub = computed(() => hubStore.myHubs[0] ?? null);

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
        fetchHubBookings(hub.id, { date_from: todayStr, date_to: todayStr })
      ])
    )
  );

  const allPending: BookingDetail[] = [];
  const allToday: BookingDetail[] = [];

  for (const [pending, today] of results) {
    allPending.push(...pending);
    allToday.push(...today);
  }

  pendingBookings.value = allPending.sort(
    (a, b) =>
      new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
  );

  todayBookings.value = allToday.sort(
    (a, b) =>
      new Date(a.start_time).getTime() - new Date(b.start_time).getTime()
  );
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
  const name = [
    booking.booked_by_user?.first_name,
    booking.booked_by_user?.last_name,
  ]
    .filter((part): part is string => Boolean(part?.trim()))
    .join(' ');

  return name || booking.guest_name || 'Guest';
}

function hubNameForBooking(booking: BookingDetail) {
  const hub = hubStore.myHubs.find((h) => h.id === booking.court?.hub_id);
  return hub?.name ?? '—';
}

const statusConfig: Record<string, { label: string; color: string }> = {
  pending_payment: {
    label: 'Pending Payment',
    color: 'text-amber-600 bg-amber-50'
  },
  payment_sent: { label: 'Payment Sent', color: 'text-blue-600 bg-blue-50' },
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
      <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <!-- Pending Payments -->
        <NuxtLink
          to="/dashboard/hubs"
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
          to="/dashboard/hubs"
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
            <p class="text-sm text-[#64748b]">Today's Bookings</p>
            <p class="text-2xl font-bold text-[#0f1728]">
              {{ todayConfirmedCount }}
            </p>
          </div>
        </NuxtLink>

        <!-- Active Hubs -->
        <NuxtLink
          to="/dashboard/hubs"
          class="flex items-center gap-4 rounded-2xl border border-[#dbe4ef] bg-white p-5 transition hover:shadow-md"
        >
          <div
            class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-[#e8f0f8]"
          >
            <UIcon
              name="i-heroicons-building-office-2"
              class="h-6 w-6 text-[#004e89]"
            />
          </div>
          <div>
            <p class="text-sm text-[#64748b]">Active Hubs</p>
            <p class="text-2xl font-bold text-[#0f1728]">
              {{ activeHubsCount }}
            </p>
          </div>
        </NuxtLink>
      </div>

      <div class="grid gap-6 lg:grid-cols-2">
        <!-- ── Pending Bookings ──────────────────────────────────────────────── -->
        <div class="rounded-2xl border border-[#dbe4ef] bg-white">
          <div
            class="flex items-center justify-between border-b border-[#dbe4ef] px-5 py-4"
          >
            <h2 class="text-sm font-semibold text-[#0f1728]">
              Pending Payments
            </h2>
            <NuxtLink
              to="/dashboard/hubs"
              class="text-xs font-medium text-[#004e89] hover:underline"
            >
              View all
            </NuxtLink>
          </div>

          <!-- Empty state -->
          <div
            v-if="!pendingBookings.length"
            class="flex flex-col items-center justify-center px-5 py-10 text-center"
          >
            <UIcon
              name="i-heroicons-check-circle"
              class="h-8 w-8 text-green-400"
            />
            <p class="mt-2 text-sm text-[#64748b]">No pending payments</p>
          </div>

          <!-- List -->
          <ul v-else class="divide-y divide-[#f0f4f8]">
            <li
              v-for="booking in pendingBookings.slice(0, 10)"
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
              to="/dashboard/hubs"
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

    <BookingVerifyModal v-model:open="isVerifyModalOpen" :hub="verifyHub" />
  </div>
</template>
