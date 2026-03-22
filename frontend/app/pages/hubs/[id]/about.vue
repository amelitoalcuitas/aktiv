<script setup lang="ts">
import maplibregl from 'maplibre-gl';
import type { Hub, Court } from '~/types/hub';
import type { CalendarBooking, SelectedSlot } from '~/types/booking';
import { useAuthStore } from '~/stores/auth';

definePageMeta({ layout: 'hub' });

const route = useRoute();
const { fetchCourts } = useHubs();
const { fetchHubBookings } = useBooking();
const authStore = useAuthStore();

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

const hasCoords = computed(
  () => hub.value?.lat != null && hub.value?.lng != null
);

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
const bookingsMap = ref<Record<number, CalendarBooking[]>>({});

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

watch(
  () => courts.value,
  () => loadAllBookings(),
  { immediate: true }
);

watch(selectedDate, () => loadAllBookings());

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
const pendingReceiptCourtId = ref<number | null>(null);
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
      <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[1fr_320px]">
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
            <h2 class="text-lg font-bold text-[var(--aktiv-ink)]">About this hub</h2>
            <p
              v-if="hub.description"
              class="mt-1 whitespace-pre-wrap text-base leading-relaxed text-[var(--aktiv-muted)]"
            >
              {{ hub.description }}
            </p>
            <p v-else class="mt-1 text-sm italic text-[var(--aktiv-muted)]">
              No description yet.
              <NuxtLink
                :to="`/dashboard/hubs/${hubId}/edit`"
                class="not-italic text-[var(--aktiv-primary)] hover:underline"
                >Add one →</NuxtLink
              >
            </p>
          </div>

          <!-- Contact + Websites (2-column row) -->
          <div
            v-if="(hub.contact_numbers && hub.contact_numbers.length > 0) || (hub.websites && hub.websites.length > 0)"
            class="grid grid-cols-1 divide-y divide-[var(--aktiv-border)] sm:grid-cols-2 sm:divide-x sm:divide-y-0"
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

            <!-- Websites -->
            <div
              v-if="hub.websites && hub.websites.length > 0"
              class="p-4 md:p-6"
            >
              <h2 class="text-lg font-bold text-[var(--aktiv-ink)]">
                Websites
              </h2>
              <ul class="mt-2 space-y-1">
                <li v-for="(site, i) in hub.websites" :key="i">
                  <a
                    :href="site.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="break-all text-sm text-[var(--aktiv-primary)] hover:underline"
                    >{{ site.url }}</a
                  >
                </li>
              </ul>
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
                class="flex flex-col gap-1.5 rounded-xl border border-[var(--aktiv-border)] bg-[var(--aktiv-bg)] p-3"
              >
                <div class="flex items-start justify-between gap-2">
                  <p
                    class="text-sm font-semibold leading-tight text-[var(--aktiv-ink)]"
                  >
                    {{ court.name }}
                  </p>
                  <UBadge
                    :label="court.indoor ? 'Indoor' : 'Outdoor'"
                    color="secondary"
                    variant="subtle"
                    class="shrink-0"
                  />
                </div>
                <span class="text-lg font-black text-[var(--aktiv-primary)]">
                  ₱{{
                    parseFloat(court.price_per_hour).toLocaleString('en-PH')
                  }}
                  <span class="text-xs font-normal text-[var(--aktiv-muted)]"
                    >/ hr</span
                  >
                </span>
                <div
                  class="flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-[var(--aktiv-muted)]"
                >
                  <span
                    v-if="court.surface"
                    class="inline-flex items-center gap-1 capitalize"
                  >
                    <UIcon name="i-heroicons-squares-2x2" class="h-3 w-3" />
                    {{ court.surface }}
                  </span>
                  <span
                    v-if="court.max_players"
                    class="inline-flex items-center gap-1"
                  >
                    <UIcon name="i-heroicons-users" class="h-3 w-3" />
                    Max {{ court.max_players }}
                  </span>
                </div>
                <div
                  v-if="court.sports.length > 0"
                  class="flex flex-wrap gap-1"
                >
                  <UBadge
                    v-for="sport in court.sports"
                    :key="sport"
                    :label="sport"
                    variant="outline"
                    color="neutral"
                    class="capitalize"
                  />
                </div>
              </div>
            </div>
            <p v-else class="mt-1 text-sm text-[var(--aktiv-muted)]">
              No courts listed.
            </p>
          </div>

          <!-- 4. Schedule (resource grid) -->
          <div
            v-if="courts && courts.length > 0"
            id="schedule"
            class="p-4 md:p-6"
          >
            <h2 class="mb-4 text-base font-bold text-[var(--aktiv-ink)]">
              Schedule
            </h2>
            <div class="space-y-4">
              <SchedulerContactNotice
                :contact-numbers="hub?.contact_numbers ?? []"
                :websites="hub?.websites ?? []"
              />
              <SchedulerResourceGrid
                :courts="courts ?? []"
                :bookings-map="bookingsMap"
                :selected-date="selectedDate"
                :selected-slots="selectedSlots"
                :min-time="gridMinTime"
                :max-time="gridMaxTime"
                :operating-hours="hub?.operating_hours ?? []"
                @slot-click="onSlotClick"
                @update:selected-date="selectedDate = $event"
                @own-booking-click="onOwnBookingClick"
              />
            </div>
          </div>

          <!-- 5. Map -->
          <div v-if="hasCoords" class="overflow-hidden">
            <div ref="mapContainer" class="h-[320px] w-full" />
          </div>
        </div>

        <!-- Right: sticky booking summary (desktop only) -->
        <div class="hidden lg:block lg:sticky lg:top-[160px]">
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

      <!-- Mobile floating booking bar -->
      <div
        v-if="courts && courts.length > 0"
        class="fixed bottom-0 left-0 right-0 z-40 p-4 lg:hidden"
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
