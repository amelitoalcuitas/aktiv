<script setup lang="ts">
import type { Court } from '~/types/hub';
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
  }>(),
  {
    minTime: '06:00',
    maxTime: '23:00',
    selectedSlots: () => []
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

const timeSlots = computed<string[]>(() => {
  const start = parseMinutes(props.minTime);
  const end = parseMinutes(props.maxTime);
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

// ── Cell state ─────────────────────────────────────────────────
interface CellState {
  type: 'past' | 'available' | 'booked';
  booking: CalendarBooking | null;
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
      if (start <= now.value) return { type: 'past', booking: null };
      return { type: 'available', booking: null };
    });
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
      return '#fee2e2';
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
      return '#9f1239';
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

// ── Native date-picker ────────────────────────────────────────
const dateInput = useTemplateRef<HTMLInputElement>('dateInput');

const todayStr = todayMidnight.toISOString().slice(0, 10);

const selectedDateStr = computed(() => {
  const d = props.selectedDate;
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
});

function openDatePicker() {
  dateInput.value?.showPicker?.();
  dateInput.value?.click();
}

function onDateInputChange(e: Event) {
  const val = (e.target as HTMLInputElement).value;
  if (!val) return;
  const [y, mo, day] = val.split('-').map(Number);
  const d = new Date(props.selectedDate);
  d.setFullYear(y ?? 0, (mo ?? 1) - 1, day ?? 1);
  d.setHours(0, 0, 0, 0);
  emit('update:selectedDate', d);
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
    class="overflow-hidden rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] shadow-sm"
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

      <!-- Clickable date label — opens native date picker -->
      <div class="relative">
        <input
          ref="dateInput"
          type="date"
          :value="selectedDateStr"
          :min="todayStr"
          class="sr-only"
          tabindex="-1"
          aria-hidden="true"
          @change="onDateInputChange"
        />
        <button
          type="button"
          class="flex items-center gap-1.5 rounded-md px-2 py-1 transition-colors hover:bg-[var(--aktiv-border)]"
          @click="openDatePicker"
        >
          <UIcon
            name="i-heroicons-calendar-days"
            class="h-4 w-4 text-[var(--aktiv-muted)]"
          />
          <span class="text-sm font-semibold text-[var(--aktiv-ink)]">
            {{ headerLabel }}
          </span>
        </button>
      </div>

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
              class="sticky left-0 top-0 z-30 w-20 min-w-[80px] border-b border-r border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] px-3 py-3 text-left text-sm font-medium text-[var(--aktiv-muted)]"
            >
              Time
            </th>
            <!-- Court column headers: sticky top -->
            <th
              v-for="court in courts"
              :key="court.id"
              class="sticky top-0 z-20 min-w-[144px] border-b border-r border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] px-3 py-3 text-left last:border-r-0"
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
              class="sticky left-0 z-10 w-20 min-w-[80px] border-r border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] px-3 py-2 text-sm font-medium text-[var(--aktiv-muted)]"
            >
              {{ formatTimeLabel(slot) }}
            </td>

            <!-- Court cells -->
            <td
              v-for="court in courts"
              :key="court.id"
              class="min-w-[144px] border-r border-[var(--aktiv-border)] p-1.5 last:border-r-0"
            >
              <!-- Past slot -->
              <div
                v-if="getCellState(court.id, slotIdx).type === 'past'"
                class="h-12 rounded-md bg-[var(--aktiv-border)] opacity-40"
              />

              <!-- Booked slot -->
              <template
                v-else-if="getCellState(court.id, slotIdx).type === 'booked'"
              >
                <!-- Own pending_payment booking: clickable to upload receipt -->
                <button
                  v-if="
                    getCellBooking(court.id, slotIdx).is_own &&
                    getCellBooking(court.id, slotIdx).status ===
                      'pending_payment'
                  "
                  type="button"
                  class="flex h-12 w-full items-center justify-center gap-1 rounded-md px-2 text-center text-sm font-medium transition-opacity hover:opacity-70 active:scale-95"
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
                  class="flex h-12 items-center justify-center rounded-md px-2 text-center text-sm font-medium"
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
                  'flex h-12 w-full items-center justify-center gap-1 rounded-md text-sm font-semibold transition-colors active:scale-95',
                  isSlotSelected(court.id, slotIdx)
                    ? 'bg-[var(--aktiv-primary)] text-white hover:bg-[var(--aktiv-primary-hover)]'
                    : 'bg-[var(--aktiv-success-bg)] text-[var(--aktiv-success-fg)] hover:brightness-95'
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
          class="inline-block h-3.5 w-3.5 rounded-sm bg-[var(--aktiv-success-bg)]"
        />
        <span class="text-sm text-[var(--aktiv-muted)]">Available</span>
      </div>
      <div class="flex items-center gap-1.5">
        <span class="inline-block h-3.5 w-3.5 rounded-sm bg-[#fef9c3]" />
        <span class="text-sm text-[var(--aktiv-muted)]"
          >Pending (tap to upload receipt)</span
        >
      </div>
      <div class="flex items-center gap-1.5">
        <span class="inline-block h-3.5 w-3.5 rounded-sm bg-[#fee2e2]" />
        <span class="text-sm text-[var(--aktiv-muted)]">Reserved</span>
      </div>
      <div class="flex items-center gap-1.5">
        <span
          class="inline-block h-3.5 w-3.5 rounded-sm bg-[var(--aktiv-border)] opacity-40"
        />
        <span class="text-sm text-[var(--aktiv-muted)]">Past / Closed</span>
      </div>
    </div>
  </div>
</template>
