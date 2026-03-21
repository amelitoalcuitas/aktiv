<script setup lang="ts">
import type { Court, OperatingHoursEntry } from '~/types/hub';
import type {
  CalendarBooking,
  BookingStatus,
  SelectedSlot
} from '~/types/booking';

const props = withDefaults(
  defineProps<{
    courts: Court[];
    bookingsMap: Record<number, CalendarBooking[]>;
    selectedDate: Date;
    minTime?: string;
    maxTime?: string;
    selectedSlots?: SelectedSlot[];
    operatingHours?: OperatingHoursEntry[];
  }>(),
  {
    minTime: '06:00',
    maxTime: '23:00',
    selectedSlots: () => [],
    operatingHours: () => []
  }
);

const emit = defineEmits<{
  'slot-click': [{ court: Court; date: Date }];
  'update:selectedDate': [Date];
  'own-booking-click': [{ booking: CalendarBooking; court: Court }];
}>();

// ── Time slot generation ───────────────────────────────────────
function parseMinutes(t: string): number {
  const parts = t.split(':');
  return Number(parts[0] ?? 0) * 60 + Number(parts[1] ?? 0);
}

const effectiveMinTime = computed(() => {
  if (!props.operatingHours.length) return props.minTime;
  const entry = props.operatingHours.find(
    (e) => e.day_of_week === props.selectedDate.getDay()
  );
  if (!entry || entry.is_closed) return props.minTime;
  return entry.opens_at;
});

const effectiveMaxTime = computed(() => {
  if (!props.operatingHours.length) return props.maxTime;
  const entry = props.operatingHours.find(
    (e) => e.day_of_week === props.selectedDate.getDay()
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
  const parts = timeStr.split(':');
  const h = Number(parts[0] ?? 0);
  const m = Number(parts[1] ?? 0);
  const d = new Date(props.selectedDate);
  d.setHours(h, m, 0, 0);
  return d;
}

// ── Closed day detection ───────────────────────────────────────
const isDayClosed = computed(() => {
  if (!props.operatingHours.length) return false;
  const dow = props.selectedDate.getDay();
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
onUnmounted(() => clearInterval(nowTimer));

const grid = computed<Record<number, CellState[]>>(() => {
  const result: Record<number, CellState[]> = {};
  for (const court of props.courts) {
    const bookings = props.bookingsMap[court.id] ?? [];
    result[court.id] = timeSlots.value.map((slot) => {
      const start = slotStartDate(slot);
      const slotEndMs = start.getTime() + 3_600_000;
      const booking = getBookingForSlot(bookings, start.getTime(), slotEndMs);
      if (booking) return { type: 'booked', booking };
      if (isDayClosed.value) return { type: 'closed', booking: null };
      if (start <= now.value) return { type: 'past', booking: null };
      return { type: 'available', booking: null };
    });
  }
  return result;
});

// ── Merged grid (rowspan for consecutive same-booking slots) ───
const mergedGrid = computed<Record<number, MergedCell[]>>(() => {
  const result: Record<number, MergedCell[]> = {};
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

// ── Price label ────────────────────────────────────────────────
function priceLabel(court: Court): string | null {
  const n = parseFloat(court.price_per_hour);
  if (isNaN(n)) return null;
  return `₱${n.toLocaleString('en-PH', { maximumFractionDigits: 0 })}/hr`;
}

// ── Day navigation ─────────────────────────────────────────────
const todayMidnight = (() => {
  const d = new Date();
  d.setHours(0, 0, 0, 0);
  return d;
})();

const canGoPrev = computed(() => {
  const prev = new Date(props.selectedDate);
  prev.setHours(0, 0, 0, 0);
  prev.setDate(prev.getDate() - 1);
  return prev >= todayMidnight;
});

function prevDay() {
  if (!canGoPrev.value) return;
  const d = new Date(props.selectedDate);
  d.setDate(d.getDate() - 1);
  emit('update:selectedDate', d);
}

function nextDay() {
  const d = new Date(props.selectedDate);
  d.setDate(d.getDate() + 1);
  emit('update:selectedDate', d);
}

const headerLabel = computed(() =>
  props.selectedDate.toLocaleDateString('en-PH', {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    year: 'numeric'
  })
);

// ── Selected slot check ──────────────────────────────────────
function isSlotSelected(courtId: number, slotIdx: number): boolean {
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
  const nowH = new Date().getHours();
  const idx = timeSlots.value.findIndex((slot) => {
    const h = parseInt(slot.split(':')[0] ?? '0', 10);
    return h >= nowH;
  });
  // Show one row above current time for context; each row ≈ 64px
  scrollWrapper.value.scrollTop = Math.max(0, (idx - 1) * 64);
}

onMounted(() => nextTick(scrollToCurrentTime));

watch(
  () => props.selectedDate,
  () =>
    nextTick(() => {
      if (!scrollWrapper.value) return;
      const isToday =
        new Date().toDateString() === props.selectedDate.toDateString();
      if (isToday) scrollToCurrentTime();
      else scrollWrapper.value.scrollTop = 0;
    })
);

// ── Safe cell accessors (avoid repeated optional chaining in template) ──
function getCellState(courtId: number, slotIdx: number): CellState {
  return grid.value[courtId]?.[slotIdx] ?? { type: 'past', booking: null };
}

// Only safe to call when getCellState(...).type === 'booked'
function getCellBooking(courtId: number, slotIdx: number): CalendarBooking {
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
    <div ref="scrollWrapper" class="overflow-auto max-h-[700px]">
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
              <div class="flex flex-col gap-1">
                <span
                  class="text-sm font-semibold leading-tight text-[var(--aktiv-ink)]"
                >
                  {{ court.name }}
                </span>
                <UBadge color="neutral" variant="soft" size="sm">
                  {{ court.indoor ? 'Indoor' : 'Outdoor' }}
                </UBadge>
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
                  class="h-12 rounded-md bg-[#fee2e2] flex items-center justify-center"
                >
                  <span
                    class="text-xs font-bold tracking-widest text-[#991b1b] uppercase"
                    >Closed</span
                  >
                </div>

                <!-- Past slot -->
                <div
                  v-else-if="getCellState(court.id, slotIdx).type === 'past'"
                  class="h-12 rounded-md bg-[#f1f5f9] opacity-50"
                />

                <!-- Booked slot -->
                <template
                  v-else-if="getCellState(court.id, slotIdx).type === 'booked'"
                >
                  <!-- Invisible spacer keeps the td height so borders render -->
                  <div class="h-12 w-full" aria-hidden="true" />
                  <!-- Own pending_payment booking: clickable to upload receipt -->
                  <button
                    v-if="
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
                    'flex cursor-pointer h-12 w-full items-center justify-center gap-1 rounded-md text-sm font-semibold transition-colors active:scale-95',
                    isSlotSelected(court.id, slotIdx)
                      ? 'bg-[var(--aktiv-primary)] text-white hover:bg-[var(--aktiv-primary-hover)]'
                      : 'bg-[#dbeafe] text-[var(--aktiv-primary)] border border-dashed border-[#93c5fd] hover:brightness-95'
                  ]"
                  @click="handleCellClick(court, slotIdx)"
                >
                  <UIcon
                    v-if="isSlotSelected(court.id, slotIdx)"
                    name="i-heroicons-check"
                    class="h-3.5 w-3.5 shrink-0"
                  />
                  {{ priceLabel(court) ?? 'Book' }}
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
        <span class="text-sm text-[var(--aktiv-muted)]"
          >Pending (tap to upload receipt)</span
        >
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
    </div>
  </div>
</template>
