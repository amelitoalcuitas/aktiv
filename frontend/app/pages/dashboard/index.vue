<script setup lang="ts">
import type { BookingDetail } from '~/types/booking';
import type { Court, HubEvent } from '~/types/hub';
import type { DashboardCalendarItem as DashboardCalendarApiItem } from '~/composables/useDashboardCalendar';
import type {
  DashboardOverviewHub,
  DashboardOverviewSummary
} from '~/composables/useDashboardOverview';
import { useDashboardOverview } from '~/composables/useDashboardOverview';
import type { OpenPlaySession } from '~/types/openPlay';
import { getOpenPlaySessionPresentation } from '~/utils/openPlayPresentation';
import { useHubStore } from '~/stores/hub';

definePageMeta({ layout: 'dashboard', middleware: ['auth', 'owner'] });

useHead({ title: 'Dashboard · Aktiv' });

const hubStore = useHubStore();
const { fetchCourts } = useHubs();
const { fetchDashboardCalendar } = useDashboardCalendar();
const { fetchDashboardOverview } = useDashboardOverview();
const { fetchEvent } = useHubEvents();
const { fetchSession } = useOwnerOpenPlay();
const toast = useToast();

// ── State ──────────────────────────────────────────────────────────────────────

interface DashboardCalendarItem {
  id: string;
  kind: 'event' | 'open_play';
  hubId: string;
  hubName: string;
  title: string;
  date: string;
  timeLabel?: string;
  to: string;
}

const loading = ref(true);
const overviewSummary = ref<DashboardOverviewSummary>({
  needs_review_count: 0,
  pending_payments_count: 0,
  today_confirmed_count: 0,
  revenue_today: 0
});
const overviewHubs = ref<DashboardOverviewHub[]>([]);
const actionNeededBookings = ref<BookingDetail[]>([]);
const todayScheduleBookings = ref<BookingDetail[]>([]);
const calendarItems = ref<DashboardCalendarItem[]>([]);
const calendarLoading = ref(false);
const visibleCalendarMonth = ref(
  new Date()
    .toLocaleDateString('en-CA', { timeZone: 'Asia/Manila' })
    .slice(0, 7)
);
const calendarItemsByMonth = ref<Record<string, DashboardCalendarItem[]>>({});
const calendarRequestsByMonth = new Map<string, Promise<void>>();

const isHubPickerOpen = ref(false);
const isWalkInModalOpen = ref(false);
const walkInHubId = ref<string | null>(null);
const walkInCourts = ref<Court[]>([]);
const walkInLoading = ref(false);
const walkInInitialDate = ref<string | undefined>(undefined);
const walkInInitialHour = ref<number | undefined>(undefined);
const isCalendarDetailOpen = ref(false);
const selectedCalendarItem = ref<DashboardCalendarItem | null>(null);
const calendarDetailLoading = ref(false);
const calendarDetailError = ref<string | null>(null);
const selectedCalendarEvent = ref<HubEvent | null>(null);
const selectedOpenPlaySession = ref<OpenPlaySession | null>(null);

// ── Computed ───────────────────────────────────────────────────────────────────

const activeHubsCount = computed(
  () => overviewHubs.value.filter((hub) => hub.is_active).length
);

const hubPerformanceRows = computed(() =>
  [...overviewHubs.value].sort((a, b) => {
    const actionDelta =
      b.needs_review_count +
      b.pending_payments_count -
      (a.needs_review_count + a.pending_payments_count);

    if (actionDelta !== 0) return actionDelta;
    if (b.revenue_today !== a.revenue_today) {
      return b.revenue_today - a.revenue_today;
    }

    return a.hub_name.localeCompare(b.hub_name);
  })
);

const selectedOpenPlayPresentation = computed(() =>
  selectedOpenPlaySession.value
    ? getOpenPlaySessionPresentation(selectedOpenPlaySession.value)
    : null
);

// ── Init ──────────────────────────────────────────────────────────────────────

onMounted(async () => {
  try {
    await Promise.all([hubStore.fetchMyHubs(), loadOverviewData()]);

    if (hubStore.myHubs.length) {
      await loadCalendarData(visibleCalendarMonth.value);
    }
  } finally {
    loading.value = false;
  }
});

async function loadOverviewData() {
  try {
    const overview = await fetchDashboardOverview();
    overviewSummary.value = overview.summary;
    overviewHubs.value = overview.hubs;
    actionNeededBookings.value = overview.action_needed;
    todayScheduleBookings.value = overview.today_schedule;
  } catch {
    toast.add({ title: 'Failed to load dashboard overview', color: 'error' });
    overviewSummary.value = {
      needs_review_count: 0,
      pending_payments_count: 0,
      today_confirmed_count: 0,
      revenue_today: 0
    };
    overviewHubs.value = [];
    actionNeededBookings.value = [];
    todayScheduleBookings.value = [];
  }
}

async function loadCalendarData(monthKey: string) {
  if (calendarItemsByMonth.value[monthKey]) {
    calendarItems.value = calendarItemsByMonth.value[monthKey] ?? [];
    return;
  }

  const inFlightRequest = calendarRequestsByMonth.get(monthKey);
  if (inFlightRequest) {
    await inFlightRequest;
    calendarItems.value = calendarItemsByMonth.value[monthKey] ?? [];
    return;
  }

  const { dateFrom, dateTo } = getMonthRange(monthKey);

  const request = (async () => {
    calendarLoading.value = true;

    try {
      const items = await fetchDashboardCalendar({
        date_from: dateFrom,
        date_to: dateTo
      });
      const normalizedItems: DashboardCalendarItem[] = items.map((item: DashboardCalendarApiItem) => ({
        id: item.id,
        kind: item.kind,
        hubId: item.hub_id,
        hubName: item.hub_name,
        title: item.title,
        date: item.date,
        timeLabel: item.time_label ?? undefined,
        to: item.to
      }));

      calendarItemsByMonth.value[monthKey] = normalizedItems;
      calendarItems.value = normalizedItems;
    } catch {
      toast.add({ title: 'Failed to load calendar items', color: 'error' });
      calendarItems.value = [];
    } finally {
      calendarRequestsByMonth.delete(monthKey);
      calendarLoading.value = false;
    }
  })();

  calendarRequestsByMonth.set(monthKey, request);
  await request;
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

function parseCalendarEventId(itemId: string): string | null {
  const [, eventId] = itemId.split(':');
  return eventId ?? null;
}

function parseCalendarOpenPlayId(itemId: string): string | null {
  const [, sessionId] = itemId.split(':');
  return sessionId ?? null;
}

async function handleCalendarItemClick(item: DashboardCalendarItem) {
  selectedCalendarItem.value = item;
  selectedCalendarEvent.value = null;
  selectedOpenPlaySession.value = null;
  calendarDetailError.value = null;
  calendarDetailLoading.value = true;
  isCalendarDetailOpen.value = true;

  try {
    if (item.kind === 'event') {
      const eventId = parseCalendarEventId(item.id);

      if (!eventId) {
        throw new Error('Invalid event identifier.');
      }

      selectedCalendarEvent.value = await fetchEvent(item.hubId, eventId);
      return;
    }

    const sessionId = parseCalendarOpenPlayId(item.id);

    if (!sessionId) {
      throw new Error('Invalid open play identifier.');
    }

    selectedOpenPlaySession.value = await fetchSession(item.hubId, sessionId);
  } catch {
    calendarDetailError.value = 'Failed to load calendar item details.';
    toast.add({
      title: 'Failed to load calendar item details',
      color: 'error'
    });
  } finally {
    calendarDetailLoading.value = false;
  }
}

function closeCalendarDetailModal() {
  isCalendarDetailOpen.value = false;
}

async function retryCalendarItemDetail() {
  if (!selectedCalendarItem.value) return;
  await handleCalendarItemClick(selectedCalendarItem.value);
}

async function goToCalendarItemPage() {
  if (!selectedCalendarItem.value?.to) return;

  isCalendarDetailOpen.value = false;
  await navigateTo(selectedCalendarItem.value.to);
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

function formatCalendarDate(date: string) {
  const [year, month, day] = date.split('-').map(Number);

  return new Date(year, (month ?? 1) - 1, day ?? 1).toLocaleDateString(
    'en-PH',
    {
      timeZone: 'Asia/Manila',
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    }
  );
}

function formatCalendarDateRange(dateFrom: string, dateTo: string) {
  if (dateFrom === dateTo) {
    return formatCalendarDate(dateFrom);
  }

  return `${formatCalendarDate(dateFrom)} - ${formatCalendarDate(dateTo)}`;
}

function formatClockTime(value: string | null) {
  if (!value) return null;

  const [hours, minutes = '00'] = value.split(':');
  const date = new Date(2026, 0, 1, Number(hours), Number(minutes), 0, 0);

  return date.toLocaleTimeString('en-PH', {
    timeZone: 'Asia/Manila',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  });
}

function formatEventTimeRange(event: HubEvent) {
  const start = formatClockTime(event.time_from);
  const end = formatClockTime(event.time_to);

  if (!start) return 'All day';
  if (!end) return start;

  return `${start} - ${end}`;
}

function eventDisplayTitle(event: HubEvent) {
  if (event.title) return event.title;
  if (event.event_type === 'voucher' && event.voucher_code) {
    return `Voucher ${event.voucher_code}`;
  }

  return 'Untitled event';
}

function formatEventTypeLabel(eventType: HubEvent['event_type']) {
  return eventType
    .split('_')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
}

function eventTypeBadgeClass(eventType: HubEvent['event_type']) {
  if (eventType === 'closure') return 'bg-red-50 text-red-700';
  if (eventType === 'promo') return 'bg-amber-50 text-amber-700';
  if (eventType === 'voucher') return 'bg-green-50 text-green-700';

  return 'bg-blue-50 text-blue-700';
}

function formatEventDiscount(event: HubEvent): string | null {
  if (event.event_type !== 'promo' && event.event_type !== 'voucher') {
    return null;
  }

  if (event.court_discounts?.length) {
    return `${event.court_discounts.length} court${event.court_discounts.length === 1 ? '' : 's'} with custom discounts`;
  }

  if (!event.discount_value) return null;

  if (event.discount_type === 'percent') {
    return `${parseFloat(event.discount_value)}% off`;
  }

  return `₱${parseFloat(event.discount_value).toFixed(0)} off`;
}

function formatVoucherLimits(event: HubEvent): string | null {
  if (event.event_type !== 'voucher') return null;

  const parts: string[] = [];

  if (event.limit_total_uses && event.max_total_uses !== null) {
    parts.push(
      `${event.max_total_uses} total use${event.max_total_uses === 1 ? '' : 's'}`
    );
  }

  if (event.limit_per_user_uses && event.max_uses_per_user !== null) {
    parts.push(`${event.max_uses_per_user} per user`);
  }

  return parts.length ? parts.join(' · ') : null;
}

function formatOpenPlaySchedule(session: OpenPlaySession) {
  if (!session.booking) return 'Schedule unavailable';

  const start = new Date(session.booking.start_time);
  const end = new Date(session.booking.end_time);

  return `${start.toLocaleDateString('en-PH', {
    timeZone: 'Asia/Manila',
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  })} · ${start.toLocaleTimeString('en-PH', {
    timeZone: 'Asia/Manila',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  })} - ${end.toLocaleTimeString('en-PH', {
    timeZone: 'Asia/Manila',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  })}`;
}

function formatOpenPlayPrice(session: OpenPlaySession) {
  const price = Number(session.price_per_player);

  if (price === 0) return 'Free session';

  return `₱${price.toLocaleString('en-PH')} / player`;
}

function formatOpenPlayParticipants(session: OpenPlaySession) {
  return `${session.participants_count} / ${session.max_players} players reserved`;
}

function bookerName(booking: BookingDetail) {
  const u = booking.booked_by_user;
  const name = u ? `${u.first_name ?? ''} ${u.last_name ?? ''}`.trim() : '';

  return name || booking.guest_name || 'Guest';
}

function hubNameForBooking(booking: BookingDetail) {
  if (booking.hub_name) return booking.hub_name;
  const hub = hubStore.myHubs.find((h) => h.id === booking.court?.hub_id);
  return hub?.name ?? '—';
}

function formatCurrency(value: number) {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(value);
}

async function refreshOverviewContent() {
  await loadOverviewData();

  if (!hubStore.myHubs.length) return;

  delete calendarItemsByMonth.value[visibleCalendarMonth.value];
  await loadCalendarData(visibleCalendarMonth.value);
}

function getMonthRange(monthKey: string): { dateFrom: string; dateTo: string } {
  const [year, month] = monthKey.split('-').map(Number);
  const start = new Date(year, month - 1, 1);
  const end = new Date(year, month, 0);

  return {
    dateFrom: `${start.getFullYear()}-${String(start.getMonth() + 1).padStart(2, '0')}-${String(start.getDate()).padStart(2, '0')}`,
    dateTo: `${end.getFullYear()}-${String(end.getMonth() + 1).padStart(2, '0')}-${String(end.getDate()).padStart(2, '0')}`
  };
}

function expiresIn(iso: string | null): string | null {
  if (!iso) return null;
  const mins = Math.round((new Date(iso).getTime() - Date.now()) / 60000);
  if (mins <= 0) return 'Expiring';
  if (mins < 60) return `${mins}m left`;
  return `${Math.floor(mins / 60)}h left`;
}

async function handleCalendarMonthChange(monthStart: string) {
  const monthKey = monthStart.slice(0, 7);
  if (monthKey === visibleCalendarMonth.value && calendarItems.value.length) {
    return;
  }

  visibleCalendarMonth.value = monthKey;
  await loadCalendarData(monthKey);
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
      <div
        v-if="!hubStore.myHubs.length"
        class="rounded-2xl border border-dashed border-[#dbe4ef] bg-white p-12 text-center"
      >
        <UIcon
          name="i-heroicons-building-office-2"
          class="mx-auto h-12 w-12 text-[#c8d5e0]"
        />
        <h2 class="mt-4 text-base font-semibold text-[#0f1728]">No hubs yet</h2>
        <p class="mt-1 text-sm text-[#64748b]">
          Create your first hub to start tracking bookings and revenue here.
        </p>
        <UButton to="/dashboard/hubs/create" class="mt-5" icon="i-heroicons-plus">
          Create Hub
        </UButton>
      </div>

      <template v-else>
      <!-- ── Stat Cards ──────────────────────────────────────────────────────── -->
      <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Needs Review -->
        <div
          class="flex items-center gap-4 rounded-2xl border border-[#dbe4ef] bg-white p-5"
        >
          <div
            class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-blue-50"
          >
            <UIcon name="i-heroicons-eye" class="h-6 w-6 text-blue-500" />
          </div>
          <div>
            <p class="text-sm text-[#64748b]">Needs Review</p>
            <p class="text-2xl font-bold text-[#0f1728]">
              {{ overviewSummary.needs_review_count }}
            </p>
          </div>
        </div>

        <!-- Pending Payments -->
        <div
          class="flex items-center gap-4 rounded-2xl border border-[#dbe4ef] bg-white p-5"
        >
          <div
            class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-amber-50"
          >
            <UIcon name="i-heroicons-clock" class="h-6 w-6 text-amber-500" />
          </div>
          <div>
            <p class="text-sm text-[#64748b]">Pending Payments</p>
            <p class="text-2xl font-bold text-[#0f1728]">
              {{ overviewSummary.pending_payments_count }}
            </p>
          </div>
        </div>

        <!-- Today's Confirmed -->
        <div
          class="flex items-center gap-4 rounded-2xl border border-[#dbe4ef] bg-white p-5"
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
              {{ overviewSummary.today_confirmed_count }}
            </p>
          </div>
        </div>

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
              {{ formatCurrency(overviewSummary.revenue_today) }}
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

      <div class="mb-6 rounded-2xl border border-[#dbe4ef] bg-white">
        <div
          class="flex items-center justify-between border-b border-[#dbe4ef] px-5 py-4"
        >
          <div>
            <h2 class="text-sm font-semibold text-[#0f1728]">Hub Performance</h2>
            <p class="mt-1 text-xs text-[#64748b]">
              {{ activeHubsCount }} active of {{ overviewHubs.length }}
              {{ overviewHubs.length === 1 ? 'hub' : 'hubs' }} in your portfolio
              today.
            </p>
          </div>
          <NuxtLink
            to="/dashboard/hubs"
            class="text-xs font-medium text-[#004e89] hover:underline"
          >
            Manage hubs
          </NuxtLink>
        </div>

        <div class="overflow-hidden rounded-b-2xl">
          <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-[#f0f4f8]">
            <thead class="bg-[#f8fafc]">
              <tr class="text-left text-xs uppercase tracking-wide text-[#64748b]">
                <th class="px-5 py-3 font-medium">Hub</th>
                <th class="px-5 py-3 font-medium">Needs Review</th>
                <th class="px-5 py-3 font-medium">Pending</th>
                <th class="px-5 py-3 font-medium">Confirmed</th>
                <th class="px-5 py-3 font-medium">Revenue</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#f0f4f8]">
              <tr
                v-for="hub in hubPerformanceRows"
                :key="hub.hub_id"
                class="cursor-pointer text-sm text-[#0f1728] transition hover:bg-[#f8fafc]"
                @click="navigateTo(`/dashboard/hubs/${hub.hub_id}/bookings`)"
              >
                <td class="px-5 py-4">
                  <div class="flex items-center gap-3">
                    <span
                      class="h-2.5 w-2.5 flex-shrink-0 rounded-full"
                      :class="hub.is_active ? 'bg-green-500' : 'bg-[#c8d5e0]'"
                    />
                    <div>
                      <p class="font-medium">{{ hub.hub_name }}</p>
                      <p class="text-xs text-[#64748b]">
                        {{ hub.is_active ? 'Active' : 'Inactive' }}
                      </p>
                    </div>
                  </div>
                </td>
                <td class="px-5 py-4">{{ hub.needs_review_count }}</td>
                <td class="px-5 py-4">{{ hub.pending_payments_count }}</td>
                <td class="px-5 py-4">{{ hub.today_confirmed_count }}</td>
                <td class="px-5 py-4">
                  {{ formatCurrency(hub.revenue_today) }}
                </td>
              </tr>
            </tbody>
          </table>
          </div>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-2">
        <!-- ── Action Needed ────────────────────────────────────────────────── -->
        <div class="rounded-2xl border border-[#dbe4ef] bg-white">
          <div
            class="flex items-center justify-between border-b border-[#dbe4ef] px-5 py-4"
          >
            <h2 class="text-sm font-semibold text-[#0f1728]">Action Needed</h2>
            <NuxtLink
              to="/dashboard/hubs"
              class="text-xs font-medium text-[#004e89] hover:underline"
            >
              Manage hubs
            </NuxtLink>
          </div>

          <!-- Empty state -->
          <div
            v-if="!actionNeededBookings.length"
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
              v-for="booking in actionNeededBookings"
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
              to="/dashboard/hubs"
              class="text-xs font-medium text-[#004e89] hover:underline"
            >
              Manage hubs
            </NuxtLink>
          </div>

          <!-- Empty state -->
          <div
            v-if="!todayScheduleBookings.length"
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
              v-for="booking in todayScheduleBookings"
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

      <div class="mt-6">
        <DashboardMonthCalendar
          :items="calendarItems"
          :loading="calendarLoading"
          :initial-month="`${visibleCalendarMonth}-01`"
          @month-change="handleCalendarMonthChange"
          @item-click="handleCalendarItemClick"
        />
      </div>
      </template>
    </template>

    <AppModal
      v-model:open="isCalendarDetailOpen"
      :title="selectedCalendarItem?.kind === 'event' ? 'Event Details' : 'Open Play Details'"
      :confirm="undefined"
      cancel="Close"
      @cancel="closeCalendarDetailModal"
    >
      <template #body>
        <div v-if="calendarDetailLoading" class="space-y-3 py-2">
          <div class="flex items-center gap-2 text-sm text-[#64748b]">
            <UIcon name="i-heroicons-arrow-path" class="h-4 w-4 animate-spin" />
            <span>Loading details...</span>
          </div>
        </div>

        <div
          v-else-if="calendarDetailError"
          class="rounded-xl border border-dashed border-[#dbe4ef] bg-[#f8fafc] p-4"
        >
          <p class="text-sm font-medium text-[#0f1728]">
            {{ calendarDetailError }}
          </p>
          <p class="mt-1 text-sm text-[#64748b]">
            Please try again or open the source page directly.
          </p>

          <UButton class="mt-4" variant="outline" color="neutral" @click="retryCalendarItemDetail">
            Retry
          </UButton>
        </div>

        <div
          v-else-if="selectedCalendarEvent"
          class="space-y-4 text-sm text-[#0f1728]"
        >
          <div class="flex flex-wrap items-center gap-2">
            <span
              class="rounded-full px-2.5 py-1 text-xs font-medium"
              :class="eventTypeBadgeClass(selectedCalendarEvent.event_type)"
            >
              {{ formatEventTypeLabel(selectedCalendarEvent.event_type) }}
            </span>
            <span
              class="rounded-full px-2.5 py-1 text-xs font-medium"
              :class="
                selectedCalendarEvent.is_active
                  ? 'bg-green-50 text-green-700'
                  : 'bg-[#f0f4f8] text-[#64748b]'
              "
            >
              {{ selectedCalendarEvent.is_active ? 'Active' : 'Inactive' }}
            </span>
          </div>

          <div>
            <p class="text-lg font-semibold text-[#0f1728]">
              {{ eventDisplayTitle(selectedCalendarEvent) }}
            </p>
            <p class="mt-1 text-sm text-[#64748b]">
              {{ selectedCalendarItem?.hubName }}
            </p>
          </div>

          <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl bg-[#f8fafc] p-3">
              <p class="text-xs font-medium uppercase tracking-wide text-[#64748b]">
                Date
              </p>
              <p class="mt-1 font-medium">
                {{
                  formatCalendarDateRange(
                    selectedCalendarEvent.date_from,
                    selectedCalendarEvent.date_to
                  )
                }}
              </p>
            </div>
            <div class="rounded-xl bg-[#f8fafc] p-3">
              <p class="text-xs font-medium uppercase tracking-wide text-[#64748b]">
                Time
              </p>
              <p class="mt-1 font-medium">
                {{ formatEventTimeRange(selectedCalendarEvent) }}
              </p>
            </div>
          </div>

          <div v-if="selectedCalendarEvent.description" class="rounded-xl bg-[#f8fafc] p-3">
            <p class="text-xs font-medium uppercase tracking-wide text-[#64748b]">
              Description
            </p>
            <p class="mt-1 whitespace-pre-line">
              {{ selectedCalendarEvent.description }}
            </p>
          </div>

          <div
            v-if="selectedCalendarEvent.event_type === 'voucher' && selectedCalendarEvent.voucher_code"
            class="rounded-xl bg-[#f8fafc] p-3"
          >
            <p class="text-xs font-medium uppercase tracking-wide text-[#64748b]">
              Voucher Code
            </p>
            <p class="mt-1 font-semibold tracking-wide">
              {{ selectedCalendarEvent.voucher_code }}
            </p>
          </div>

          <div
            v-if="formatEventDiscount(selectedCalendarEvent) || formatVoucherLimits(selectedCalendarEvent)"
            class="grid gap-3 sm:grid-cols-2"
          >
            <div
              v-if="formatEventDiscount(selectedCalendarEvent)"
              class="rounded-xl bg-[#f8fafc] p-3"
            >
              <p class="text-xs font-medium uppercase tracking-wide text-[#64748b]">
                Discount
              </p>
              <p class="mt-1 font-medium">
                {{ formatEventDiscount(selectedCalendarEvent) }}
              </p>
            </div>
            <div
              v-if="formatVoucherLimits(selectedCalendarEvent)"
              class="rounded-xl bg-[#f8fafc] p-3"
            >
              <p class="text-xs font-medium uppercase tracking-wide text-[#64748b]">
                Usage Limits
              </p>
              <p class="mt-1 font-medium">
                {{ formatVoucherLimits(selectedCalendarEvent) }}
              </p>
            </div>
          </div>
        </div>

        <div
          v-else-if="selectedOpenPlaySession"
          class="space-y-4 text-sm text-[#0f1728]"
        >
          <div class="flex flex-wrap items-center gap-2">
            <span
              class="rounded-full px-2.5 py-1 text-xs font-medium"
              :class="{
                'bg-blue-50 text-blue-700': selectedOpenPlayPresentation?.color === 'primary',
                'bg-amber-50 text-amber-700': selectedOpenPlayPresentation?.color === 'warning',
                'bg-cyan-50 text-cyan-700': selectedOpenPlayPresentation?.color === 'info',
                'bg-green-50 text-green-700': selectedOpenPlayPresentation?.color === 'success',
                'bg-red-50 text-red-700': selectedOpenPlayPresentation?.color === 'error',
                'bg-[#f0f4f8] text-[#64748b]': selectedOpenPlayPresentation?.color === 'neutral'
              }"
            >
              {{ selectedOpenPlayPresentation?.label ?? 'Open Play' }}
            </span>
          </div>

          <div>
            <p class="text-lg font-semibold text-[#0f1728]">
              {{ selectedOpenPlaySession.title }}
            </p>
            <p class="mt-1 text-sm text-[#64748b]">
              {{ selectedCalendarItem?.hubName }}
            </p>
          </div>

          <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl bg-[#f8fafc] p-3">
              <p class="text-xs font-medium uppercase tracking-wide text-[#64748b]">
                Court
              </p>
              <p class="mt-1 font-medium">
                {{ selectedOpenPlaySession.booking?.court?.name ?? 'Court unavailable' }}
              </p>
            </div>
            <div class="rounded-xl bg-[#f8fafc] p-3">
              <p class="text-xs font-medium uppercase tracking-wide text-[#64748b]">
                Schedule
              </p>
              <p class="mt-1 font-medium">
                {{ formatOpenPlaySchedule(selectedOpenPlaySession) }}
              </p>
            </div>
            <div class="rounded-xl bg-[#f8fafc] p-3">
              <p class="text-xs font-medium uppercase tracking-wide text-[#64748b]">
                Players
              </p>
              <p class="mt-1 font-medium">
                {{ formatOpenPlayParticipants(selectedOpenPlaySession) }}
              </p>
            </div>
            <div class="rounded-xl bg-[#f8fafc] p-3">
              <p class="text-xs font-medium uppercase tracking-wide text-[#64748b]">
                Price
              </p>
              <p class="mt-1 font-medium">
                {{ formatOpenPlayPrice(selectedOpenPlaySession) }}
              </p>
            </div>
          </div>

          <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl bg-[#f8fafc] p-3">
              <p class="text-xs font-medium uppercase tracking-wide text-[#64748b]">
                Guests Can Join
              </p>
              <p class="mt-1 font-medium">
                {{ selectedOpenPlaySession.guests_can_join ? 'Yes' : 'No' }}
              </p>
            </div>
            <div class="rounded-xl bg-[#f8fafc] p-3">
              <p class="text-xs font-medium uppercase tracking-wide text-[#64748b]">
                Created
              </p>
              <p class="mt-1 font-medium">
                {{ formatDateTime(selectedOpenPlaySession.created_at) }}
              </p>
            </div>
          </div>

          <div v-if="selectedOpenPlaySession.description" class="rounded-xl bg-[#f8fafc] p-3">
            <p class="text-xs font-medium uppercase tracking-wide text-[#64748b]">
              Description
            </p>
            <p class="mt-1 whitespace-pre-line">
              {{ selectedOpenPlaySession.description }}
            </p>
          </div>
        </div>
      </template>

      <template #footer>
        <div class="flex w-full flex-col gap-2 sm:flex-row sm:justify-end">
          <UButton
            color="neutral"
            variant="ghost"
            class="w-full justify-center sm:w-auto"
            @click="closeCalendarDetailModal"
          >
            Close
          </UButton>
          <UButton
            v-if="selectedCalendarItem?.to"
            color="primary"
            class="w-full justify-center sm:w-auto"
            @click="goToCalendarItemPage"
          >
            Go to page
          </UButton>
        </div>
      </template>
    </AppModal>

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
      @created="refreshOverviewContent"
      @openplay:created="refreshOverviewContent"
    />
  </div>
</template>
