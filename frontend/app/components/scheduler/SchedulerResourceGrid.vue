<script setup lang="ts">
import type { Court, OperatingHoursEntry, HubEvent } from '~/types/hub';
import type {
  CalendarBooking,
  BookingStatus,
  SelectedSlot
} from '~/types/booking';
import type { OpenPlaySession } from '~/types/openPlay';
import { getOpenPlayParticipantPresentation } from '~/utils/openPlayPresentation';

const props = withDefaults(
  defineProps<{
    courts: Court[];
    bookingsMap: Record<string, CalendarBooking[]>;
    selectedDate: Date;
    minTime?: string;
    maxTime?: string;
    selectedSlots?: SelectedSlot[];
    openPlaySessionsMap?: Record<string, OpenPlaySession>;
    operatingHours?: OperatingHoursEntry[];
    closureEvents?: HubEvent[];
    promoEvents?: HubEvent[];
    timeZone?: string | null;
    loading?: boolean;
  }>(),
  {
    minTime: '06:00',
    maxTime: '23:00',
    selectedSlots: () => [],
    openPlaySessionsMap: () => ({}),
    operatingHours: () => [],
    closureEvents: () => [],
    promoEvents: () => [],
    timeZone: null,
    loading: false
  }
);

const emit = defineEmits<{
  'slot-click': [{ court: Court; date: Date }];
  'update:selectedDate': [Date];
  'own-booking-click': [{ booking: CalendarBooking; court: Court }];
  'open-play-click': [OpenPlaySession];
}>();

const weekdayMap: Record<string, number> = {
  Sun: 0,
  Mon: 1,
  Tue: 2,
  Wed: 3,
  Thu: 4,
  Fri: 5,
  Sat: 6
};

function getSelectedDateKey(date: Date): string {
  return getDateKeyInTimezone(date, props.timeZone);
}

function getSelectedWeekday(date: Date): number {
  return weekdayMap[
    formatInHubTimezone(date, { weekday: 'short' }, 'en-US', props.timeZone)
  ] ?? 0;
}

function buildSlotDate(timeStr: string): Date {
  const [year, month, day] = getSelectedDateKey(props.selectedDate).split('-').map(Number);
  const [hour, minute] = timeStr.split(':').map(Number);

  return buildUtcDateFromHubLocalParts(
    { year, month, day, hour, minute, second: 0 },
    props.timeZone
  );
}

// ── Time slot generation ───────────────────────────────────────
function parseMinutes(t: string): number {
  const parts = t.split(':');
  return Number(parts[0] ?? 0) * 60 + Number(parts[1] ?? 0);
}

const effectiveMinTime = computed(() => {
  if (!props.operatingHours.length) return props.minTime;
  const entry = props.operatingHours.find(
    (e) => e.day_of_week === getSelectedWeekday(props.selectedDate)
  );
  if (!entry || entry.is_closed) return props.minTime;
  return entry.opens_at;
});

const effectiveMaxTime = computed(() => {
  if (!props.operatingHours.length) return props.maxTime;
  const entry = props.operatingHours.find(
    (e) => e.day_of_week === getSelectedWeekday(props.selectedDate)
  );
  if (!entry || entry.is_closed) return props.maxTime;
  return entry.closes_at;
});

const timeSlots = computed<string[]>(() => {
  const start = parseMinutes(effectiveMinTime.value);
  const end = parseMinutes(effectiveMaxTime.value);
  const slots: string[] = [];
  for (let min = start; min < end; min += 60) {
    const h = Math.floor(min / 60);
    slots.push(`${String(h).padStart(2, '0')}:00`);
  }
  return slots;
});

function formatTimeLabel(t: string): string {
  const h = parseInt(t.split(':')[0] ?? '0', 10);
  if (h === 0) return '12 AM';
  if (h < 12) return `${h} AM`;
  if (h === 12) return '12 PM';
  return `${h - 12} PM`;
}

function slotStartDate(timeStr: string): Date {
  return buildSlotDate(timeStr);
}

// ── Closure event detection ────────────────────────────────────
function isCourtClosedByEvent(courtId: string, slotTime?: string): boolean {
  if (!props.closureEvents?.length) return false;
  const dateStr = formatDateString(props.selectedDate);
  return props.closureEvents.some((e) => {
    const eventStartDate = getDateKeyInTimezone(e.start_time, props.timeZone);
    const eventEndDate = getDateKeyInTimezone(e.end_time, props.timeZone);
    if (eventStartDate > dateStr || eventEndDate < dateStr) return false;
    if (e.affected_courts !== null && !e.affected_courts.includes(courtId)) {
      return false;
    }

    if (slotTime) {
      const slotStart = buildSlotDate(slotTime);
      const slotEnd = new Date(slotStart.getTime() + 3_600_000);
      return new Date(e.start_time) < slotEnd && new Date(e.end_time) > slotStart;
    }

    const [year, month, day] = dateStr.split('-').map(Number);
    const dayStart = buildUtcDateFromHubLocalParts({ year, month, day, hour: 0, minute: 0, second: 0 }, props.timeZone);
    const dayEnd = buildUtcDateFromHubLocalParts({ year, month, day, hour: 23, minute: 59, second: 59 }, props.timeZone);

    return new Date(e.start_time) <= dayEnd && new Date(e.end_time) >= dayStart;
  });
}

function formatDateString(date: Date): string {
  return getSelectedDateKey(date);
}

// ── Closed day detection ───────────────────────────────────────
const isDayClosed = computed(() => {
  if (!props.operatingHours.length) return false;
  const dow = getSelectedWeekday(props.selectedDate);
  const entry = props.operatingHours.find((e) => e.day_of_week === dow);
  return entry?.is_closed ?? false;
});

// ── Cell state ─────────────────────────────────────────────────
interface CellState {
  type: 'past' | 'available' | 'booked' | 'closed';
  booking: CalendarBooking | null;
}

interface MergedCell extends CellState {
  rowspan: number;
  skip: boolean;
}

function getBookingForSlot(
  bookings: CalendarBooking[],
  slotStartMs: number,
  slotEndMs: number
): CalendarBooking | null {
  return (
    bookings.find((b) => {
      const bs = new Date(b.start_time).getTime();
      const be = new Date(b.end_time).getTime();
      return bs < slotEndMs && be > slotStartMs;
    }) ?? null
  );
}

// Refresh "past" state every minute
const now = ref(new Date());
let nowTimer: ReturnType<typeof setInterval>;
onMounted(() => {
  nowTimer = setInterval(() => {
    now.value = new Date();
  }, 60_000);
});

const isMobile = ref(false);
onMounted(() => {
  const mq = window.matchMedia('(max-width: 768px)');
  isMobile.value = mq.matches;
  mq.addEventListener('change', (e) => {
    isMobile.value = e.matches;
  });
});
onUnmounted(() => clearInterval(nowTimer));

const grid = computed<Record<string, CellState[]>>(() => {
  const result: Record<string, CellState[]> = {};
  for (const court of props.courts) {
    const bookings = props.bookingsMap[court.id] ?? [];
    result[court.id] = timeSlots.value.map((slot) => {
      const start = slotStartDate(slot);
      const slotEndMs = start.getTime() + 3_600_000;
      const booking = getBookingForSlot(bookings, start.getTime(), slotEndMs);
      if (booking) return { type: 'booked', booking };
      if (isDayClosed.value || isCourtClosedByEvent(court.id, slot))
        return { type: 'closed', booking: null };
      if (start <= now.value) return { type: 'past', booking: null };
      return { type: 'available', booking: null };
    });
  }
  return result;
});

// ── Merged grid (rowspan for consecutive same-booking slots) ───
const mergedGrid = computed<Record<string, MergedCell[]>>(() => {
  const result: Record<string, MergedCell[]> = {};
  for (const court of props.courts) {
    const flat = grid.value[court.id] ?? [];
    const merged: MergedCell[] = flat.map((c) => ({
      ...c,
      rowspan: 1,
      skip: false
    }));
    for (let i = 0; i < merged.length; i++) {
      if (merged[i]!.skip || merged[i]!.type !== 'booked') continue;
      let span = 1;
      while (
        i + span < merged.length &&
        merged[i + span]!.type === 'booked' &&
        merged[i + span]!.booking?.id === merged[i]!.booking?.id
      ) {
        merged[i + span]!.skip = true;
        span++;
      }
      merged[i]!.rowspan = span;
    }
    result[court.id] = merged;
  }
  return result;
});

// ── Booking display ────────────────────────────────────────────
function bookingLabel(booking: CalendarBooking): string {
  if (booking.is_own) return 'Your Booking';
  if (booking.session_type === 'open_play') return 'Open Play';
  if (booking.status === 'pending_payment' || booking.status === 'payment_sent')
    return 'Pending';
  return 'Reserved';
}

function getOpenPlaySession(booking: CalendarBooking | null): OpenPlaySession | null {
  if (!booking || booking.session_type !== 'open_play') return null;
  return props.openPlaySessionsMap[booking.id] ?? null;
}

function openPlayPriceLabel(session: OpenPlaySession): string {
  const price = Number(session.price_per_player);
  if (price === 0) return 'Free';
  return `P${price.toLocaleString('en-PH', { maximumFractionDigits: 0 })}/pax`;
}

function openPlayViewerLabel(session: OpenPlaySession): string | null {
  if (!session.viewer_participant) return null;

  return getOpenPlayParticipantPresentation(session.viewer_participant).label;
}

function bookingBg(status: BookingStatus): string {
  switch (status) {
    case 'pending_payment':
    case 'payment_sent':
      return '#fef9c3';
    case 'confirmed':
      return '#dcfce7';
    default:
      return '#f1f5f9';
  }
}

function bookingTextColor(status: BookingStatus): string {
  switch (status) {
    case 'pending_payment':
    case 'payment_sent':
      return '#92400e';
    case 'confirmed':
      return '#166534';
    default:
      return '#475569';
  }
}

// ── Promo discount helpers ──────────────────────────────────────
function calculateDiscountedPrice(
  originalPrice: number,
  discountType: string | null | undefined,
  discountValue: string | null | undefined
): number {
  const parsedValue = parseFloat(discountValue ?? '0');

  if (discountType === 'percent') {
    return originalPrice * (1 - parsedValue / 100);
  }

  if (discountType === 'flat') {
    return Math.max(0, originalPrice - parsedValue);
  }

  return originalPrice;
}

function getSlotPriceInfo(court: Court, slotTime: string): {
  effectivePrice: number;
  originalPrice: number;
  hasDiscount: boolean;
} {
  const original = parseFloat(court.price_per_hour);
  const slotStart = buildSlotDate(slotTime);
  const slotEnd = new Date(slotStart.getTime() + 3_600_000);

  const activePromos = props.promoEvents.filter((e) => {
    if (e.event_type !== 'promo') return false;
    if (e.affected_courts?.length && !e.affected_courts.includes(court.id))
      return false;
    return new Date(e.start_time) < slotEnd && new Date(e.end_time) > slotStart;
  });

  if (!activePromos.length)
    return {
      effectivePrice: original,
      originalPrice: original,
      hasDiscount: false
    };

  const effective = activePromos.reduce((bestPrice, promo) => {
    const courtDiscount = promo.court_discounts?.find(
      (cd) => cd.court_id === court.id
    );
    const candidatePrice = calculateDiscountedPrice(
      original,
      courtDiscount?.discount_type ?? promo.discount_type,
      courtDiscount?.discount_value ?? promo.discount_value
    );

    return Math.min(bestPrice, candidatePrice);
  }, original);

  return {
    effectivePrice: effective,
    originalPrice: original,
    hasDiscount: effective < original
  };
}

// ── Price label ────────────────────────────────────────────────
function priceLabel(court: Court): string | null {
  const n = parseFloat(court.price_per_hour);
  if (isNaN(n)) return null;
  return `₱${n.toLocaleString('en-PH', { maximumFractionDigits: 0 })}/hr`;
}

// ── Day navigation ─────────────────────────────────────────────
const canGoPrev = computed(() => {
  const [year, month, day] = getSelectedDateKey(props.selectedDate).split('-').map(Number);
  const prev = new Date(Date.UTC(year, month - 1, day, 12));
  prev.setUTCDate(prev.getUTCDate() - 1);
  const prevKey = `${prev.getUTCFullYear()}-${String(prev.getUTCMonth() + 1).padStart(2, '0')}-${String(prev.getUTCDate()).padStart(2, '0')}`;

  return prevKey >= getTodayDateKeyInTimezone(props.timeZone);
});

function prevDay() {
  if (!canGoPrev.value) return;
  const [year, month, day] = getSelectedDateKey(props.selectedDate).split('-').map(Number);
  const prev = new Date(Date.UTC(year, month - 1, day, 12));
  prev.setUTCDate(prev.getUTCDate() - 1);
  emit('update:selectedDate', buildUtcDateFromHubLocalParts({
    year: prev.getUTCFullYear(),
    month: prev.getUTCMonth() + 1,
    day: prev.getUTCDate()
  }, props.timeZone));
}

function nextDay() {
  const [year, month, day] = getSelectedDateKey(props.selectedDate).split('-').map(Number);
  const next = new Date(Date.UTC(year, month - 1, day, 12));
  next.setUTCDate(next.getUTCDate() + 1);
  emit('update:selectedDate', buildUtcDateFromHubLocalParts({
    year: next.getUTCFullYear(),
    month: next.getUTCMonth() + 1,
    day: next.getUTCDate()
  }, props.timeZone));
}

const headerLabel = computed(() =>
  formatInHubTimezone(props.selectedDate, {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    year: 'numeric'
  }, 'en-PH', props.timeZone)
);

// ── Selected slot check ──────────────────────────────────────
function isSlotSelected(courtId: string, slotIdx: number): boolean {
  if (!props.selectedSlots?.length) return false;
  const slot = timeSlots.value[slotIdx];
  if (!slot) return false;
  const t = slotStartDate(slot).getTime();
  return props.selectedSlots.some(
    (s) => s.courtId === courtId && s.slotStart.getTime() === t
  );
}

// ── Auto-scroll to current time ───────────────────────────────
const scrollWrapper = useTemplateRef<HTMLDivElement>('scrollWrapper');

function scrollToCurrentTime() {
  if (!scrollWrapper.value) return;
  const nowH = Number(formatInHubTimezone(new Date(), {
    hour: '2-digit',
    hour12: false
  }, 'en-US', props.timeZone));
  const idx = timeSlots.value.findIndex((slot) => {
    const h = parseInt(slot.split(':')[0] ?? '0', 10);
    return h >= nowH;
  });
  // Show one row above current time for context; each row ≈ 72px
  scrollWrapper.value.scrollTop = Math.max(0, (idx - 1) * 72);
}

onMounted(() => nextTick(scrollToCurrentTime));

watch(
  () => props.selectedDate,
  () =>
    nextTick(() => {
      if (!scrollWrapper.value) return;
      const isToday =
        getSelectedDateKey(props.selectedDate) === getTodayDateKeyInTimezone(props.timeZone);
      if (isToday) scrollToCurrentTime();
      else scrollWrapper.value.scrollTop = 0;
    })
);

// ── Safe cell accessors (avoid repeated optional chaining in template) ──
function getCellState(courtId: string, slotIdx: number): CellState {
  return grid.value[courtId]?.[slotIdx] ?? { type: 'past', booking: null };
}

// Only safe to call when getCellState(...).type === 'booked'
function getCellBooking(courtId: string, slotIdx: number): CalendarBooking {
  // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
  return grid.value[courtId]![slotIdx]!.booking!;
}

// ── Slot click ─────────────────────────────────────────────────
function handleCellClick(court: Court, slotIdx: number) {
  const cell = getCellState(court.id, slotIdx);
  if (cell.type !== 'available') return;
  const slot = timeSlots.value[slotIdx];
  if (!slot) return;
  const date = slotStartDate(slot);
  emit('slot-click', { court, date });
}

function handleBookedCellClick(court: Court, slotIdx: number) {
  const cell = getCellState(court.id, slotIdx);
  if (cell.type !== 'booked' || !cell.booking) return;
  const session = getOpenPlaySession(cell.booking);
  if (session) {
    emit('open-play-click', session);
    return;
  }
  if (cell.booking.is_own && cell.booking.status === 'pending_payment') {
    emit('own-booking-click', { booking: cell.booking, court });
  }
}
</script>

<template>
  <div
    class="overflow-hidden rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)]"
  >
    <!-- Date nav header -->
    <div
      class="flex items-center justify-between border-b border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] px-4 py-3"
    >
      <button
        type="button"
        :disabled="!canGoPrev"
        class="flex h-8 w-8 items-center justify-center rounded-md transition-colors hover:bg-[var(--aktiv-border)] disabled:cursor-default disabled:opacity-30"
        @click="prevDay"
      >
        <UIcon name="i-heroicons-chevron-left" class="h-5 w-5" />
      </button>

      <AppDatePicker
        variant="nav"
        :model-value="selectedDate"
        :label="headerLabel"
        :allow-past="false"
        @update:model-value="emit('update:selectedDate', $event)"
      />

      <button
        type="button"
        class="flex h-8 w-8 items-center justify-center rounded-md transition-colors hover:bg-[var(--aktiv-border)]"
        @click="nextDay"
      >
        <UIcon name="i-heroicons-chevron-right" class="h-5 w-5" />
      </button>
    </div>

    <!--
      Grid: time = rows (vertical scroll), courts = columns (horizontal scroll
      only when courts exceed screen width — far fewer columns than 17 time slots).
      Both sticky court header and sticky time column are supported via overflow-auto
      on this wrapper.
    -->
    <div ref="scrollWrapper" class="relative overflow-auto max-h-[700px]">
      <div
        v-if="loading"
        class="absolute inset-0 z-30 flex flex-col gap-3 bg-white/75 p-4 backdrop-blur-[1px]"
      >
        <USkeleton class="h-12 w-full rounded-lg" />
        <div class="grid grid-cols-4 gap-3">
          <USkeleton
            v-for="index in 12"
            :key="index"
            class="h-14 w-full rounded-md"
          />
        </div>
      </div>
      <table class="w-full border-collapse" style="min-width: max-content">
        <thead>
          <tr>
            <!-- Corner: sticky top + left -->
            <th
              class="sticky left-0 top-0 z-20 w-16 min-w-[64px] border-b border-r border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] px-2 py-3 text-left text-sm font-medium text-[var(--aktiv-muted)]"
            >
              Time
            </th>
            <!-- Court column headers: sticky top -->
            <th
              v-for="court in courts"
              :key="court.id"
              class="sticky top-0 z-10 min-w-[110px] border-b border-r border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] px-3 py-3 text-left"
            >
              <div class="flex items-center justify-between">
                <span
                  class="min-w-0 flex-1 truncate text-sm font-semibold leading-tight text-[var(--aktiv-ink)]"
                  :title="court.name"
                >
                  {{ court.name }}
                </span>
                <UPopover
                  :mode="isMobile ? 'click' : 'hover'"
                  :ui="{ content: 'p-0' }"
                >
                  <UButton
                    icon="i-lucide-image"
                    size="xs"
                    color="neutral"
                    variant="ghost"
                    aria-label="View court image"
                  />
                  <template #content>
                    <div class="w-48">
                      <img
                        v-if="court.image_url"
                        :src="court.image_url"
                        :alt="court.name"
                        class="w-full rounded object-cover"
                      />
                      <div
                        v-else
                        class="flex h-32 w-48 items-center justify-center rounded bg-[var(--aktiv-border)] text-[var(--aktiv-muted)]"
                      >
                        <UIcon name="i-lucide-image-off" class="size-8" />
                      </div>
                    </div>
                  </template>
                </UPopover>
              </div>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(slot, slotIdx) in timeSlots"
            :key="slot"
            class="border-b border-[var(--aktiv-border)] last:border-b-0"
          >
            <!-- Time label: sticky left -->
            <td
              class="sticky left-0 z-5 w-16 min-w-[64px] border-r border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] px-2 py-2 text-sm font-medium text-[var(--aktiv-muted)]"
            >
              {{ formatTimeLabel(slot) }}
            </td>

            <!-- Court cells -->
            <template v-for="court in courts" :key="court.id">
              <td
                v-if="!mergedGrid[court.id]?.[slotIdx]?.skip"
                :rowspan="mergedGrid[court.id]?.[slotIdx]?.rowspan ?? 1"
                class="relative min-w-[110px] border-b border-r border-[var(--aktiv-border)] p-1.5"
              >
                <!-- Closed slot -->
                <div
                  v-if="getCellState(court.id, slotIdx).type === 'closed'"
                  class="h-14 rounded-md bg-[#fee2e2] flex items-center justify-center"
                >
                  <span
                    class="text-xs font-bold tracking-widest text-[#991b1b] uppercase"
                    >Closed</span
                  >
                </div>

                <!-- Past slot -->
                <div
                  v-else-if="getCellState(court.id, slotIdx).type === 'past'"
                  class="h-14 rounded-md bg-[#f1f5f9] opacity-50"
                />

                <!-- Booked slot -->
                <template
                  v-else-if="getCellState(court.id, slotIdx).type === 'booked'"
                >
                  <!-- Invisible spacer keeps the td height so borders render -->
                  <div class="h-14 w-full" aria-hidden="true" />
                  <button
                    v-if="getOpenPlaySession(getCellBooking(court.id, slotIdx))"
                    type="button"
                    class="absolute inset-1.5 flex cursor-pointer flex-col items-center justify-center rounded-md border border-[#7c3aed] bg-[#8b5cf6] px-1.5 text-center text-[11px] font-semibold text-white shadow-sm transition hover:bg-[#7c3aed]"
                    @click="handleBookedCellClick(court, slotIdx)"
                  >
                    <span
                      v-if="
                        openPlayViewerLabel(
                          getOpenPlaySession(getCellBooking(court.id, slotIdx))!
                        )
                      "
                      class="mb-1 rounded-full bg-white/20 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide"
                    >
                      {{
                        openPlayViewerLabel(
                          getOpenPlaySession(getCellBooking(court.id, slotIdx))!
                        )
                      }}
                    </span>
                    <span class="truncate max-w-full">Open Play</span>
                    <span class="text-[10px] font-medium opacity-80">
                      {{
                        getOpenPlaySession(getCellBooking(court.id, slotIdx))
                          ?.participants_count
                      }}
                      /
                      {{
                        getOpenPlaySession(getCellBooking(court.id, slotIdx))
                          ?.max_players
                      }}
                      ·
                      {{
                        openPlayPriceLabel(
                          getOpenPlaySession(getCellBooking(court.id, slotIdx))!
                        )
                      }}
                    </span>
                  </button>
                  <!-- Own pending_payment booking: clickable to upload receipt -->
                  <button
                    v-else-if="
                      getCellBooking(court.id, slotIdx).is_own &&
                      getCellBooking(court.id, slotIdx).status ===
                        'pending_payment'
                    "
                    type="button"
                    class="absolute inset-1.5 flex w-auto items-center justify-center gap-1 rounded-md px-2 text-center text-sm font-medium transition-opacity hover:opacity-70 active:scale-95"
                    :style="{
                      backgroundColor: bookingBg(
                        getCellBooking(court.id, slotIdx).status
                      ),
                      color: bookingTextColor(
                        getCellBooking(court.id, slotIdx).status
                      )
                    }"
                    :title="'Upload receipt'"
                    @click="handleBookedCellClick(court, slotIdx)"
                  >
                    <UIcon
                      name="i-heroicons-arrow-up-tray"
                      class="h-3.5 w-3.5 flex-shrink-0"
                    />
                    {{ bookingLabel(getCellBooking(court.id, slotIdx)) }}
                  </button>
                  <!-- Other bookings: not clickable -->
                  <div
                    v-else
                    class="absolute inset-1.5 flex items-center justify-center rounded-md px-2 text-center text-sm font-medium"
                    :style="{
                      backgroundColor: bookingBg(
                        getCellBooking(court.id, slotIdx).status
                      ),
                      color: bookingTextColor(
                        getCellBooking(court.id, slotIdx).status
                      )
                    }"
                  >
                    {{ bookingLabel(getCellBooking(court.id, slotIdx)) }}
                  </div>
                </template>

                <!-- Available slot (unselected or selected) -->
                <button
                  v-else
                  type="button"
                  :class="[
                    'flex cursor-pointer h-14 w-full items-center justify-center gap-1 rounded-md text-sm font-semibold transition-colors active:scale-95',
                    isSlotSelected(court.id, slotIdx)
                      ? getSlotPriceInfo(court, slot).hasDiscount
                        ? 'slot-promo-selected'
                        : 'bg-[var(--aktiv-primary)] text-white hover:bg-[var(--aktiv-primary-hover)]'
                      : getSlotPriceInfo(court, slot).hasDiscount
                        ? 'slot-promo'
                        : 'bg-[#dbeafe] text-[var(--aktiv-primary)] border border-dashed border-[#93c5fd] hover:brightness-95'
                  ]"
                  @click="handleCellClick(court, slotIdx)"
                >
                  <UIcon
                    v-if="isSlotSelected(court.id, slotIdx)"
                    name="i-heroicons-check"
                    class="shrink-0 size-6"
                  />
                  <template v-else-if="getSlotPriceInfo(court, slot).hasDiscount">
                    <span
                      class="flex flex-col items-center leading-none gap-0.5"
                    >
                      <span class="text-xs line-through opacity-60">{{
                        priceLabel(court)
                      }}</span>
                      <span class="text-sm font-bold"
                        >₱{{
                          getSlotPriceInfo(
                            court,
                            slot
                          ).effectivePrice.toLocaleString('en-PH', {
                            maximumFractionDigits: 0
                          })
                        }}/hr</span
                      >
                    </span>
                  </template>
                  <template v-else>{{ priceLabel(court) ?? 'Book' }}</template>
                </button>
              </td>
            </template>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Legend -->
    <div
      class="flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-[var(--aktiv-border)] px-4 py-2.5"
    >
      <div class="flex items-center gap-1.5">
        <span
          class="inline-block h-3.5 w-3.5 rounded-sm bg-[#dbeafe] border border-dashed border-[#93c5fd]"
        />
        <span class="text-sm text-[var(--aktiv-muted)]">Available</span>
      </div>
      <div class="flex items-center gap-1.5">
        <span class="inline-block h-3.5 w-3.5 rounded-sm bg-[#dcfce7]" />
        <span class="text-sm text-[var(--aktiv-muted)]">Confirmed</span>
      </div>
      <div class="flex items-center gap-1.5">
        <span class="inline-block h-3.5 w-3.5 rounded-sm bg-[#fef9c3]" />
        <span class="text-sm text-[var(--aktiv-muted)]">Pending</span>
      </div>
      <div class="flex items-center gap-1.5">
        <span class="inline-block h-3.5 w-3.5 rounded-sm border border-[#7c3aed] bg-[#8b5cf6]" />
        <span class="text-sm text-[var(--aktiv-muted)]">Open Play</span>
      </div>
      <div class="flex items-center gap-1.5">
        <span class="inline-block h-3.5 w-3.5 rounded-sm bg-[#fee2e2]" />
        <span class="text-sm text-[var(--aktiv-muted)]">Closed</span>
      </div>
      <div class="flex items-center gap-1.5">
        <span
          class="inline-block h-3.5 w-3.5 rounded-sm bg-[var(--aktiv-border)] opacity-40"
        />
        <span class="text-sm text-[var(--aktiv-muted)]">Past</span>
      </div>
      <div v-if="promoEvents.length > 0" class="flex items-center gap-1.5">
        <span class="slot-promo inline-block h-3.5 w-3.5 rounded-sm" />
        <span class="text-sm text-[var(--aktiv-muted)]">Promo</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
@keyframes shine {
  0% {
    background-position: -200% center;
  }
  100% {
    background-position: 200% center;
  }
}

.slot-promo {
  background: linear-gradient(
    105deg,
    #d4a017 30%,
    #f5d76e 45%,
    #fce97a 52%,
    #f5d76e 59%,
    #d4a017 70%
  );
  background-size: 200% auto;
  animation: shine 3.5s linear infinite;
  color: #3b2000;
  border: 1px solid #b8860b;
}

.slot-promo:hover {
  filter: brightness(1.05);
}

.slot-promo-selected {
  background-color: #c9960c;
  color: #fff;
  border: 1px solid #a07808;
}
</style>
