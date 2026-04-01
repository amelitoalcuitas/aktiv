<script setup lang="ts">
import maplibregl from 'maplibre-gl';
import type { Hub, Court } from '~/types/hub';
import type { CalendarBooking, SelectedSlot } from '~/types/booking';
import type { OpenPlaySession } from '~/types/openPlay';
import { useAuthStore } from '~/stores/auth';
import OpenPlayJoinModal from '~/components/openplay/OpenplayJoinModal.vue';

definePageMeta({ layout: 'hub' });

const route = useRoute();
const { fetchCourts } = useHubs();
const { fetchHubBookings } = useBooking();
const { fetchSessions } = useOpenPlay();
const authStore = useAuthStore();
const toast = useToast();

const hubId = computed(() => String(route.params.id ?? ''));

const { data: hub } = useNuxtData<Hub>(`hub-${hubId.value}`);

const { data: courts, error } = await useAsyncData<Court[]>(
  `hub-courts-${hubId.value}`,
  () => fetchCourts(hubId.value)
);

// ── Owner check ────────────────────────────────────────────────────────────
const isOwner = computed(() => authStore.user?.id === hub.value?.owner_id);

// ── Today schedule helpers ─────────────────────────────────────────────────
const todayDayOfWeek = new Date().getDay();

function parseMins(time: string): number {
  const parts = time.split(':');
  const h = parseInt(parts[0] ?? '0', 10);
  const m = parseInt(parts[1] ?? '0', 10);
  return h * 60 + m;
}

const todayCloseLabel = computed(() => {
  if (!hub.value?.operating_hours?.length) return null;
  const todayHours = hub.value.operating_hours.find(
    (oh) => oh.day_of_week === new Date().getDay()
  );
  if (!todayHours || todayHours.is_closed) return null;
  const now = new Date();
  const nowMins = now.getHours() * 60 + now.getMinutes();
  const openMins = parseMins(todayHours.opens_at);
  const closeMins = parseMins(todayHours.closes_at);
  if (nowMins < openMins || nowMins >= closeMins) return null;
  const diff = closeMins - nowMins;
  const h = Math.floor(diff / 60);
  const m = diff % 60;
  return h > 0 ? `Closes in ${h}h${m > 0 ? ` ${m}m` : ''}` : `Closes in ${m}m`;
});

// ── Map ───────────────────────────────────────────────────────────────────
const mapContainer = ref<HTMLElement | null>(null);
let map: maplibregl.Map | null = null;

// ── Mobile drawer ─────────────────────────────────────────────────────────
const drawerOpen = ref(false);

const mobileTotalSlots = computed(() => selectedSlots.value.length);

const mobileGrandTotal = computed(() => {
  let total = 0;
  for (const slot of selectedSlots.value) {
    const court = courts.value?.find((c) => c.id === slot.courtId);
    if (court) total += parseFloat(court.price_per_hour);
  }
  return total;
});

// ── Scheduler: selected date & bookings ────────────────────────────────────
const selectedDate = ref(new Date());
const bookingsMap = ref<Record<string, CalendarBooking[]>>({});
const openPlaySessions = ref<OpenPlaySession[]>([]);

function formatDateString(date: Date): string {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

async function loadAllBookings() {
  if (!courts.value || courts.value.length === 0) return;
  const dateStr = formatDateString(selectedDate.value);
  try {
    bookingsMap.value = await fetchHubBookings(hubId.value, {
      date_from: dateStr,
      date_to: dateStr
    });
  } catch {
    bookingsMap.value = {};
  }
}

async function loadOpenPlaySessions() {
  try {
    openPlaySessions.value = await fetchSessions(hubId.value);
  } catch {
    openPlaySessions.value = [];
  }
}

watch(
  () => courts.value,
  () => loadAllBookings(),
  { immediate: true }
);

watch(selectedDate, () => loadAllBookings());

await loadOpenPlaySessions();

// ── Court filter ───────────────────────────────────────────────────────────
const filteredCourtId = ref<string | null>(null);

const filteredCourts = computed(() =>
  filteredCourtId.value
    ? (courts.value ?? []).filter((c) => c.id === filteredCourtId.value)
    : (courts.value ?? [])
);

const filteredCourtName = computed(
  () => courts.value?.find((c) => c.id === filteredCourtId.value)?.name ?? ''
);

function bookThisCourt(court: Court) {
  filteredCourtId.value = court.id;
  selectedSlots.value = [];
  scrollToSchedule();
}

async function copyVoucherCode(code: string) {
  try {
    await navigator.clipboard.writeText(code);
    toast.add({ title: 'Voucher code copied', color: 'success' });
  } catch {
    toast.add({ title: 'Failed to copy voucher code', color: 'error' });
  }
}

// ── Multi-slot selection ────────────────────────────────────────────────────
const selectedSlots = ref<SelectedSlot[]>([]);

function onSlotClick({ court, date }: { court: Court; date: Date }) {
  const t = date.getTime();
  const existing = selectedSlots.value;
  const HOUR = 3_600_000;

  if (existing.length === 0 || existing[0]!.courtId !== court.id) {
    selectedSlots.value = [{ courtId: court.id, slotStart: date }];
    return;
  }

  const sorted = [...existing].sort(
    (a, b) => a.slotStart.getTime() - b.slotStart.getTime()
  );
  const minT = sorted[0]!.slotStart.getTime();
  const maxT = sorted[sorted.length - 1]!.slotStart.getTime();

  if (t >= minT && t <= maxT) {
    if (t === minT) {
      selectedSlots.value = sorted.slice(1);
    } else if (t === maxT) {
      selectedSlots.value = sorted.slice(0, -1);
    } else {
      selectedSlots.value = [{ courtId: court.id, slotStart: date }];
    }
    return;
  }

  if (t === minT - HOUR) {
    selectedSlots.value = [{ courtId: court.id, slotStart: date }, ...sorted];
    return;
  }

  if (t === maxT + HOUR) {
    selectedSlots.value = [...sorted, { courtId: court.id, slotStart: date }];
    return;
  }

  selectedSlots.value = [{ courtId: court.id, slotStart: date }];
}

function onClearSlots() {
  selectedSlots.value = [];
}

function onRemoveSlots(slots: SelectedSlot[]) {
  const keys = new Set(
    slots.map((s) => `${s.courtId}-${s.slotStart.getTime()}`)
  );
  selectedSlots.value = selectedSlots.value.filter(
    (s) => !keys.has(`${s.courtId}-${s.slotStart.getTime()}`)
  );
}

function onBookingCreated() {
  // The websocket event (booking.slot.updated) triggers loadAllBookings already.
}

const selectedOpenPlaySessionId = ref<string | null>(null);
const openPlayModalOpen = ref(false);

const filteredOpenPlaySessionsMap = computed<Record<string, OpenPlaySession>>(
  () => {
    const selectedDateKey = formatDateString(selectedDate.value);

    return Object.fromEntries(
      openPlaySessions.value
        .filter((session) => {
          if (!session.booking) return false;

          return (
            new Date(session.booking.start_time).toLocaleDateString('en-CA', {
              timeZone: 'Asia/Manila'
            }) === selectedDateKey
          );
        })
        .map((session) => [session.booking_id, session])
    );
  }
);

const selectedOpenPlaySession = computed(
  () =>
    openPlaySessions.value.find(
      (session) => session.id === selectedOpenPlaySessionId.value
    ) ?? null
);

function openOpenPlaySession(session: OpenPlaySession) {
  selectedOpenPlaySessionId.value = session.id;
  openPlayModalOpen.value = true;
}

async function onOpenPlayUpdated() {
  await Promise.all([loadAllBookings(), loadOpenPlaySessions()]);
}

function scrollToSchedule() {
  const el = document.getElementById('schedule');
  if (!el) return;
  const offset = 140; // account for sticky header + tab nav
  const top = el.getBoundingClientRect().top + window.scrollY - offset;
  window.scrollTo({ top, behavior: 'smooth' });
}

// ── Operating hours → grid bounds ──────────────────────────────────────────
const gridMinTime = computed(() => {
  const oh = hub.value?.operating_hours;
  if (!oh?.length) return '06:00';
  const open = oh.filter((e) => !e.is_closed);
  if (!open.length) return '06:00';
  return open.reduce(
    (min, e) => (e.opens_at < min ? e.opens_at : min),
    open[0]!.opens_at
  );
});

const gridMaxTime = computed(() => {
  const oh = hub.value?.operating_hours;
  if (!oh?.length) return '23:00';
  const open = oh.filter((e) => !e.is_closed);
  if (!open.length) return '23:00';
  return open.reduce(
    (max, e) => (e.closes_at > max ? e.closes_at : max),
    open[0]!.closes_at
  );
});

// ── Receipt upload ──────────────────────────────────────────────────────────
const receiptModalOpen = ref(false);
const pendingReceiptBooking = ref<CalendarBooking | null>(null);
const pendingReceiptCourtId = ref<string | null>(null);
const pendingReceiptCourtName = ref('');

function onOwnBookingClick({
  booking,
  court
}: {
  booking: CalendarBooking;
  court: Court;
}) {
  pendingReceiptBooking.value = booking;
  pendingReceiptCourtId.value = court.id;
  pendingReceiptCourtName.value = court.name;
  receiptModalOpen.value = true;
}

// ── Real-time slot updates via websocket ────────────────────────────────────
// eslint-disable-next-line @typescript-eslint/no-explicit-any
let hubChannel: any = null;

onMounted(() => {
  // Map
  if (mapContainer.value && hub.value?.lat && hub.value?.lng) {
    const lat = parseFloat(hub.value.lat);
    const lng = parseFloat(hub.value.lng);
    map = new maplibregl.Map({
      container: mapContainer.value,
      style: 'https://tiles.openfreemap.org/styles/bright',
      center: [lng, lat],
      zoom: 15
    });
    new maplibregl.Marker({ color: '#004e89' })
      .setLngLat([lng, lat])
      .addTo(map);
  }

  // WebSocket
  const { $echo } = useNuxtApp();
  if (!$echo) return;
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const echo = $echo as any;
  echo.connector.pusher.connection.connect();
  hubChannel = echo.channel(`hub.${hubId.value}`);
  hubChannel.listen('.booking.slot.updated', () => {
    loadAllBookings();
  });
});

onUnmounted(() => {
  map?.remove();
  map = null;

  const { $echo } = useNuxtApp();
  if ($echo && hubChannel) {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    ($echo as any).leaveChannel(`hub.${hubId.value}`);
    hubChannel = null;
  }
});
</script>

<template>
  <div>
    <!-- Error -->
    <div
      v-if="error"
      class="rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-6"
    >
      <p class="text-[var(--aktiv-muted)]">Failed to load hub details.</p>
    </div>

    <template v-else-if="hub">
      <!-- Closure alert (non-dismissable) -->
      <template
        v-if="hub.active_events?.some((e) => e.event_type === 'closure')"
      >
        <div
          v-for="event in hub.active_events?.filter(
            (e) => e.event_type === 'closure'
          )"
          :key="event.id"
          class="mb-4 rounded-xl border border-[#fecaca] bg-[#fef2f2] px-4 py-3"
        >
          <p class="font-semibold text-[#991b1b]">
            <UIcon name="i-heroicons-x-circle" class="mr-1 inline h-4 w-4" />
            {{ event.title }}
          </p>
          <p v-if="event.description" class="mt-0.5 text-sm text-[#b91c1c]">
            {{ event.description }}
          </p>
          <p class="mt-0.5 text-sm text-[#b91c1c]">
            Closed
            {{
              event.date_from === event.date_to
                ? `on ${event.date_from}`
                : `from ${event.date_from} to ${event.date_to}`
            }}
            <template v-if="event.time_from && event.time_to">
              · {{ event.time_from }} – {{ event.time_to }}
            </template>
          </p>
        </div>
      </template>

      <!-- Announcement banner (non-dismissable) -->
      <div
        v-for="event in hub.active_events?.filter(
          (e) => e.event_type === 'announcement'
        )"
        :key="event.id"
        class="mb-4 rounded-xl border border-[#bfdbfe] bg-[#eff6ff] px-4 py-3"
      >
        <p class="font-semibold text-[#1e40af]">{{ event.title }}</p>
        <p v-if="event.description" class="mt-0.5 text-sm text-[#1d4ed8]">
          {{ event.description }}
        </p>
      </div>

      <!-- Promo banner (non-dismissable) -->
      <HubPromoAlert
        v-for="event in hub.active_events?.filter(
          (e) => e.event_type === 'promo'
        )"
        :key="event.id"
        :event="event"
        :courts="courts ?? []"
      />

      <div
        v-for="event in hub.active_events?.filter(
          (e) => e.event_type === 'voucher' && e.show_announcement
        )"
        :key="event.id"
        class="mb-4 rounded-xl border border-[#bbf7d0] bg-[#f0fdf4] px-4 py-3"
      >
        <div class="flex items-start gap-3">
          <div
            class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#dcfce7]"
          >
            <UIcon name="i-heroicons-ticket" class="h-5 w-5 text-[#166534]" />
          </div>

          <div class="min-w-0 flex-1">
            <p class="font-semibold text-[#166534]">
              {{ event.title || 'Voucher Available' }}
            </p>
            <p v-if="event.description" class="mt-0.5 text-sm text-[#15803d]">
              {{ event.description }}
            </p>
            <p class="mt-1 text-sm text-[#15803d]">
              Valid until
              <strong class="font-semibold text-[#166534]">
                {{
                  new Date(`${event.date_to}T00:00:00`).toLocaleDateString(
                    'en-PH',
                    {
                      timeZone: 'Asia/Manila',
                      month: 'long',
                      day: 'numeric',
                      year: 'numeric'
                    }
                  )
                }}
              </strong>
            </p>
            <div
              v-if="event.voucher_code"
              class="mt-1 flex flex-wrap items-center gap-2 text-sm font-medium text-[#166534]"
            >
              <span>Voucher code: {{ event.voucher_code }}</span>
              <UButton
                size="xs"
                variant="ghost"
                color="success"
                icon="i-heroicons-clipboard-document"
                @click="copyVoucherCode(event.voucher_code)"
              >
              </UButton>
            </div>
          </div>
        </div>
      </div>

      <div
        class="grid grid-cols-1 items-start gap-6 lg:grid-cols-2 xl:grid-cols-[2fr_2fr_minmax(320px,1.2fr)]"
      >
        <!-- Left: single card -->
        <div
          class="overflow-hidden rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] divide-y divide-[var(--aktiv-border)]"
        >
          <!-- 1. Gallery -->
          <div v-if="hub.gallery_images.length > 0">
            <HubGallery :images="hub.gallery_images" :hub-name="hub.name" />
          </div>

          <!-- 2. About -->
          <div v-if="hub.description || isOwner" class="p-4 md:p-6">
            <h2 class="text-lg font-bold text-[var(--aktiv-ink)]">
              About this hub
            </h2>
            <p
              v-if="hub.description"
              class="mt-1 whitespace-pre-wrap text-base leading-relaxed text-[var(--aktiv-muted)]"
            >
              {{ hub.description }}
            </p>
            <p v-else class="mt-1 text-sm italic text-[var(--aktiv-muted)]">
              No description yet.
            </p>
          </div>

          <!-- Contact + Websites (2-column row) -->
          <div
            v-if="
              (hub.contact_numbers && hub.contact_numbers.length > 0) ||
              (hub.websites && hub.websites.length > 0)
            "
            class="grid divide-x divide-[var(--aktiv-border)] grid-cols-2"
          >
            <!-- Contact Numbers -->
            <div
              v-if="hub.contact_numbers && hub.contact_numbers.length > 0"
              class="p-4 md:p-6"
            >
              <h2 class="text-lg font-bold text-[var(--aktiv-ink)]">Contact</h2>
              <ul class="mt-2 space-y-1">
                <li
                  v-for="(contact, i) in hub.contact_numbers"
                  :key="i"
                  class="flex items-center gap-2 text-sm text-[var(--aktiv-ink)]"
                >
                  <UIcon
                    :name="
                      contact.type === 'mobile'
                        ? 'i-heroicons-device-phone-mobile'
                        : 'i-heroicons-phone'
                    "
                    class="h-4 w-4 shrink-0"
                  />
                  <ULink :href="`tel:${contact.number}`">{{
                    contact.number
                  }}</ULink>
                </li>
              </ul>
            </div>

            <!-- Links -->
            <div
              v-if="hub.websites && hub.websites.length > 0"
              class="p-4 md:p-6"
            >
              <h2 class="text-lg font-bold text-[var(--aktiv-ink)]">Links</h2>
              <AppLinksList
                :links="hub.websites"
                list-class="mt-2 flex flex-wrap items-center gap-3"
                icon-class="h-5 w-5"
              />
            </div>
          </div>

          <!-- 3. Courts -->
          <div class="p-4 md:p-6">
            <h2 class="text-lg font-bold text-[var(--aktiv-ink)]">Courts</h2>
            <div
              v-if="courts && courts.length > 0"
              class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2"
            >
              <div
                v-for="court in courts"
                :key="court.id"
                class="flex flex-col overflow-hidden rounded-xl border border-[var(--aktiv-border)]"
              >
                <!-- Court image banner -->
                <div
                  class="relative h-[140px] w-full shrink-0 overflow-hidden bg-[var(--aktiv-border)]"
                >
                  <AppImageViewer
                    v-if="court.image_url"
                    :src="court.image_url"
                    :alt="court.name"
                    wrapper-class="h-full w-full cursor-pointer"
                    image-class="h-full w-full object-cover transition-transform duration-300 ease-out hover:scale-105"
                  />
                  <div
                    v-else
                    class="flex h-full w-full flex-col items-center justify-center gap-2 text-[var(--aktiv-muted)]"
                  >
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      class="h-10 w-10 opacity-40"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                      stroke-width="1"
                    >
                      <rect x="3" y="3" width="18" height="18" rx="2" />
                      <circle cx="8.5" cy="8.5" r="1.5" />
                      <path d="M21 15l-5-5L5 21" />
                    </svg>
                    <span class="text-xs font-medium opacity-50">No photo</span>
                  </div>
                </div>

                <div class="flex flex-1 flex-col gap-2 p-3">
                  <!-- Name + price -->
                  <div class="flex min-w-0 items-center justify-between gap-2">
                    <p
                      class="min-w-0 truncate font-semibold text-[var(--aktiv-ink)]"
                      :title="court.name"
                    >
                      {{ court.name }}
                    </p>
                    <span class="font-bold text-[var(--aktiv-primary)] text-xl">
                      ₱{{
                        parseFloat(court.price_per_hour).toLocaleString(
                          'en-PH'
                        )
                      }}<span
                        class="font-normal text-sm text-[var(--aktiv-muted)]"
                        >/hr</span
                      >
                    </span>
                  </div>

                  <!-- Attributes -->
                  <div
                    class="flex flex-wrap gap-x-3 text-sm gap-y-1 text-[var(--aktiv-muted)]"
                  >
                    <span class="inline-flex items-center gap-1">
                      <UIcon
                        :name="
                          court.indoor
                            ? 'i-heroicons-building-office-2'
                            : 'i-heroicons-sun'
                        "
                        class="h-4 w-4 shrink-0"
                      />
                      {{ court.indoor ? 'Indoor' : 'Outdoor' }}
                    </span>
                    <span
                      v-if="court.surface"
                      class="inline-flex items-center gap-1 capitalize"
                    >
                      <UIcon
                        name="i-heroicons-squares-2x2"
                        class="h-4 w-4 shrink-0"
                      />
                      {{ court.surface }}
                    </span>
                  </div>

                  <!-- Sports -->
                  <div
                    v-if="court.sports.length > 0"
                    class="flex flex-wrap gap-1"
                  >
                    <UBadge
                      v-for="sport in court.sports"
                      :key="sport"
                      :label="sport"
                      variant="subtle"
                      color="neutral"
                      class="capitalize text-xs"
                    />
                  </div>

                  <!-- Book this Court -->
                  <UButton
                    block
                    size="xs"
                    variant="ghost"
                    color="primary"
                    label="Book this Court"
                    icon="i-heroicons-calendar-days"
                    class="mt-auto"
                    @click="bookThisCourt(court)"
                  />
                </div>
              </div>
            </div>
            <p v-else class="mt-1 text-sm text-[var(--aktiv-muted)]">
              No courts listed.
            </p>
          </div>
        </div>

        <!-- Middle: Schedule -->
        <div
          v-if="courts && courts.length > 0"
          id="schedule"
          class="overflow-hidden rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-4 md:p-6"
        >
          <div class="mb-4 flex flex-wrap justify-between items-center gap-2">
            <h2 class="text-base font-bold text-[var(--aktiv-ink)]">
              Schedule
            </h2>
            <div v-if="filteredCourtId" class="flex gap-2 items-center">
              <span class="text-xs font-medium">Court Selected:</span>
              <UBadge
                color="primary"
                variant="subtle"
                class="cursor-pointer select-none"
                @click="filteredCourtId = null"
              >
                {{ filteredCourtName }}
                <UIcon name="i-heroicons-x-mark" class="ml-1 h-3.5 w-3.5" />
              </UBadge>
            </div>
          </div>
          <div class="space-y-4">
            <SchedulerResourceGrid
              :courts="filteredCourts"
              :bookings-map="bookingsMap"
              :selected-date="selectedDate"
              :selected-slots="selectedSlots"
              :open-play-sessions-map="filteredOpenPlaySessionsMap"
              :min-time="gridMinTime"
              :max-time="gridMaxTime"
              :operating-hours="hub?.operating_hours ?? []"
              :closure-events="
                hub?.active_events?.filter((e) => e.event_type === 'closure') ??
                []
              "
              :promo-events="
                hub?.active_events?.filter((e) => e.event_type === 'promo') ??
                []
              "
              @slot-click="onSlotClick"
              @update:selected-date="selectedDate = $event"
              @own-booking-click="onOwnBookingClick"
              @open-play-click="openOpenPlaySession"
            />
          </div>

          <!-- Map -->
          <div
            v-if="hub.lat != null && hub.lng != null"
            class="mt-4 overflow-hidden rounded-xl border border-[var(--aktiv-border)]"
          >
            <div ref="mapContainer" class="h-[220px] w-full" />
          </div>
        </div>

        <!-- Right: sticky booking summary (xl+ only) -->
        <div class="hidden xl:block xl:sticky xl:top-[160px]">
          <div
            v-if="!courts || courts.length === 0"
            class="rounded-2xl border border-dashed border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-8 text-center"
          >
            <UIcon
              name="i-heroicons-squares-2x2"
              class="mx-auto h-10 w-10 text-[var(--aktiv-border)]"
            />
            <h3 class="mt-3 text-sm font-semibold text-[var(--aktiv-ink)]">
              No courts available
            </h3>
            <p class="mt-1 text-sm text-[var(--aktiv-muted)]">
              This hub hasn't added any courts yet.
            </p>
          </div>
          <SchedulerBookingSummary
            v-else
            :selected-slots="selectedSlots"
            :courts="courts ?? []"
            :hub-id="hubId"
            :hub="hub ?? null"
            @booking-created="onBookingCreated"
            @clear="onClearSlots"
            @remove-slots="onRemoveSlots"
          />
        </div>
      </div>

      <SchedulerReceiptUploadModal
        v-model:open="receiptModalOpen"
        :booking="pendingReceiptBooking"
        :hub-id="hubId"
        :court-id="String(pendingReceiptCourtId ?? '')"
        :court-name="pendingReceiptCourtName"
        @receipt-uploaded="onBookingCreated"
      />

      <OpenPlayJoinModal
        v-model:open="openPlayModalOpen"
        :hub-id="hubId"
        :hub="hub ?? null"
        :session="selectedOpenPlaySession"
        @updated="onOpenPlayUpdated"
      />

      <!-- Mobile floating booking bar -->
      <div
        v-if="courts && courts.length > 0"
        class="fixed bottom-0 left-0 right-0 z-40 p-4 xl:hidden"
        style="
          background: var(--aktiv-surface);
          border-top: 1px solid var(--aktiv-border);
          box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.08);
        "
      >
        <!-- Slots selected: active button -->
        <button
          v-if="mobileTotalSlots > 0"
          type="button"
          class="flex w-full items-center justify-between gap-3 rounded-2xl bg-[var(--aktiv-primary)] px-5 py-4 text-white shadow-lg active:opacity-90"
          @click="drawerOpen = true"
        >
          <div class="flex items-center gap-2.5">
            <span
              class="inline-flex items-center justify-center rounded-full bg-white/20 px-2.5 py-0.5 text-sm font-bold"
            >
              {{ mobileTotalSlots }} slot{{ mobileTotalSlots !== 1 ? 's' : '' }}
            </span>
            <span class="text-sm font-semibold">Booking Summary</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="text-lg font-black">
              ₱{{
                mobileGrandTotal.toLocaleString('en-PH', {
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 2
                })
              }}
            </span>
            <UIcon name="i-heroicons-chevron-up" class="h-5 w-5 opacity-70" />
          </div>
        </button>

        <!-- No slots: CTA to scroll to schedule -->
        <button
          v-else
          type="button"
          class="flex w-full items-center justify-center gap-2 rounded-2xl bg-[var(--aktiv-primary)] px-5 py-4 text-white shadow-lg active:opacity-90"
          @click="scrollToSchedule"
        >
          <UIcon name="i-heroicons-calendar-days" class="h-5 w-5" />
          <span class="text-sm font-semibold">Book a Court</span>
        </button>
      </div>

      <!-- Mobile bottom drawer -->
      <UDrawer
        v-model:open="drawerOpen"
        direction="bottom"
        :ui="{ content: 'max-h-[85dvh]' }"
      >
        <template #content>
          <div class="overflow-y-auto p-4">
            <SchedulerBookingSummary
              :selected-slots="selectedSlots"
              :courts="courts ?? []"
              :hub-id="hubId"
              :hub="hub ?? null"
              @booking-created="onBookingCreated"
              @clear="
                () => {
                  onClearSlots();
                  drawerOpen = false;
                }
              "
              @remove-slots="onRemoveSlots"
            />
          </div>
        </template>
      </UDrawer>
    </template>

    <!-- Loading skeleton -->
    <template v-else>
      <USkeleton class="h-[600px] w-full rounded-2xl" />
    </template>
  </div>
</template>
