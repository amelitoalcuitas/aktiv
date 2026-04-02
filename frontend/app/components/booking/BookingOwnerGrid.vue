<script setup lang="ts">
import type { Court, OperatingHoursEntry } from '~/types/hub';
import type { BookingDetail, BookingStatus } from '~/types/booking';
import type { OpenPlaySession } from '~/types/openPlay';

const props = withDefaults(
  defineProps<{
    courts: Court[];
    bookings: BookingDetail[];
    selectedDate: Date;
    minTime?: string;
    maxTime?: string;
    openPlaySessionsMap?: Record<string, OpenPlaySession>;
    operatingHours?: OperatingHoursEntry[];
  }>(),
  {
    minTime: '06:00',
    maxTime: '23:00',
    openPlaySessionsMap: () => ({}),
    operatingHours: () => []
  }
);

const emit = defineEmits<{
  'book-slot': [{ court: Court; date: Date; hour: number }];
  'update:selectedDate': [Date];
  'action-confirm': [BookingDetail];
  'action-reject': [BookingDetail];
  'action-cancel': [BookingDetail];
  'view-booking': [BookingDetail];
  'view-open-play': [BookingDetail];
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
  booking: BookingDetail | null;
}

interface CellBlock extends CellState {
  slotIdx: number;
  span: number;
}

const SLOT_WIDTH_PX = 88;
const BLOCK_INNER_GUTTER_PX = 12;
const BOOKING_CONTENT_PADDING_PX = 8;
const MIN_BOOKING_CONTENT_WIDTH_PX = 72;

const now = ref(new Date());
let nowTimer: ReturnType<typeof setInterval>;
onMounted(() => {
  nowTimer = setInterval(() => {
    now.value = new Date();
  }, 60_000);
});
onUnmounted(() => clearInterval(nowTimer));

const grid = computed(() => {
  const result: Record<number, CellBlock[]> = {};
  for (const court of props.courts) {
    const courtBookings = props.bookings.filter((b) => b.court_id === court.id);
    const cells: CellState[] = timeSlots.value.map((slot) => {
      const start = slotStartDate(slot);
      const slotStartMs = start.getTime();
      const slotEndMs = slotStartMs + 3_600_000;
      const booking =
        courtBookings.find((b) => {
          const bs = new Date(b.start_time).getTime();
          const be = new Date(b.end_time).getTime();
          return bs < slotEndMs && be > slotStartMs && b.status !== 'cancelled';
        }) ?? null;

      if (booking) return { type: 'booked', booking };
      if (isDayClosed.value) return { type: 'closed', booking: null };
      if (start <= now.value) return { type: 'past', booking: null };
      return { type: 'available', booking: null };
    });

    const blocks: CellBlock[] = [];
    let currentBlock: CellBlock | null = null;
    for (let i = 0; i < cells.length; i++) {
      const cell = cells[i]!;
      if (currentBlock) {
        if (
          cell.type === currentBlock.type &&
          cell.booking?.id === currentBlock.booking?.id &&
          cell.type === 'booked'
        ) {
          currentBlock.span += 1;
        } else {
          blocks.push(currentBlock);
          currentBlock = { ...cell, slotIdx: i, span: 1 };
        }
      } else {
        currentBlock = { ...cell, slotIdx: i, span: 1 };
      }
    }
    if (currentBlock) blocks.push(currentBlock);
    result[court.id] = blocks;
  }
  return result;
});

// ── Booking display ────────────────────────────────────────────
function abbreviateName(fullName: string): string {
  const parts = fullName.trim().split(/\s+/);
  if (parts.length === 1) return fullName;
  const last = parts[parts.length - 1]!;
  return `${parts[0]} ${last.charAt(0).toUpperCase()}.`;
}

function bookingLabel(booking: BookingDetail): string {
  if (booking.session_type === 'open_play') return 'Open Play';
  if (booking.booked_by_user) return abbreviateName(`${booking.booked_by_user.first_name} ${booking.booked_by_user.last_name}`.trim());
  if (booking.guest_name) return abbreviateName(booking.guest_name);
  return 'Booked';
}

function getOpenPlaySession(booking: BookingDetail): OpenPlaySession | null {
  return props.openPlaySessionsMap[booking.id] ?? null;
}

function bookingBg(status: BookingStatus): string {
  switch (status) {
    case 'pending_payment':
    case 'payment_sent':
      return '#fef9c3'; // Yellow 100
    case 'confirmed':
      return '#dcfce7'; // Green 100
    case 'completed':
      return '#f1f5f9';
    default:
      return '#f1f5f9';
  }
}

function bookingTextColor(status: BookingStatus): string {
  switch (status) {
    case 'pending_payment':
    case 'payment_sent':
      return '#92400e'; // Yellow 800
    case 'confirmed':
      return '#166534'; // Green 800
    default:
      return '#475569';
  }
}

function statusLabel(status: BookingStatus): string {
  switch (status) {
    case 'pending_payment':
      return 'Pending';
    case 'payment_sent':
      return 'Paid (Verify)';
    case 'confirmed':
      return 'Confirmed';
    case 'completed':
      return 'Completed';
    default:
      return status;
  }
}

// ── Booking actions ────────────────────────────────────────────
function isCancellable(status: BookingStatus) {
  return !['cancelled', 'completed', 'confirmed'].includes(status);
}

function getBookingActions(booking: BookingDetail) {
  const groups: {
    label: string;
    icon: string;
    color?: 'error';
    onSelect: () => void;
  }[][] = [];

  if (
    booking.status === 'payment_sent' ||
    booking.status === 'pending_payment'
  ) {
    groups.push([
      {
        label:
          booking.status === 'payment_sent'
            ? 'Confirm Payment'
            : 'Confirm Booking',
        icon: 'i-heroicons-check-circle',
        onSelect: () => emit('action-confirm', booking)
      },
      {
        label:
          booking.status === 'payment_sent'
            ? 'Reject Receipt'
            : 'Reject Booking',
        icon: 'i-heroicons-x-circle',
        color: 'error' as const,
        onSelect: () => emit('action-reject', booking)
      }
    ]);
  }

  if (isCancellable(booking.status)) {
    groups.push([
      {
        label: 'Cancel Booking',
        icon: 'i-heroicons-x-mark',
        color: 'error' as const,
        onSelect: () => emit('action-cancel', booking)
      }
    ]);
  }
  return groups;
}

// ── Date navigation ─────────────────────────────────────────────
const headerLabel = computed(() =>
  props.selectedDate.toLocaleDateString('en-PH', {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    year: 'numeric'
  })
);

function prevDay() {
  const d = new Date(props.selectedDate);
  d.setDate(d.getDate() - 1);
  emit('update:selectedDate', d);
}

function nextDay() {
  const d = new Date(props.selectedDate);
  d.setDate(d.getDate() + 1);
  emit('update:selectedDate', d);
}

// ── Auto-scroll ───────────────────────────────────────────────
const scrollWrapper = useTemplateRef<HTMLDivElement>('scrollWrapper');
const horizontalScroll = ref(0);

function syncHorizontalScroll() {
  horizontalScroll.value = scrollWrapper.value?.scrollLeft ?? 0;
}

function handleGridScroll() {
  syncHorizontalScroll();
}

function scrollToCurrentTime() {
  if (!scrollWrapper.value) return;
  const nowH = new Date().getHours();
  const idx = timeSlots.value.findIndex((slot) => {
    const h = parseInt(slot.split(':')[0] ?? '0', 10);
    return h >= nowH;
  });
  scrollWrapper.value.scrollLeft = Math.max(0, (idx - 1) * SLOT_WIDTH_PX);
  syncHorizontalScroll();
}

function bookingBlockWidth(span: number): number {
  return span * SLOT_WIDTH_PX - BLOCK_INNER_GUTTER_PX;
}

function bookingContentStyle(block: CellBlock): { left: string; right: string } {
  const blockWidth = bookingBlockWidth(block.span);
  const hiddenWidth = Math.max(
    0,
    horizontalScroll.value - block.slotIdx * SLOT_WIDTH_PX
  );
  const maxOffset = Math.max(
    BOOKING_CONTENT_PADDING_PX,
    blockWidth - BOOKING_CONTENT_PADDING_PX - MIN_BOOKING_CONTENT_WIDTH_PX
  );
  const left = Math.min(
    Math.max(BOOKING_CONTENT_PADDING_PX, hiddenWidth + BOOKING_CONTENT_PADDING_PX),
    maxOffset
  );

  return {
    left: `${left}px`,
    right: `${BOOKING_CONTENT_PADDING_PX}px`
  };
}

onMounted(() => nextTick(() => {
  scrollToCurrentTime();
  syncHorizontalScroll();
}));

watch(
  () => props.selectedDate,
  () =>
    nextTick(() => {
      if (!scrollWrapper.value) return;
      const isToday =
        new Date().toDateString() === props.selectedDate.toDateString();
      if (isToday) scrollToCurrentTime();
      else {
        scrollWrapper.value.scrollLeft = 0;
        syncHorizontalScroll();
      }
    })
);

// ── Slot click ─────────────────────────────────────────────────
function handleCellClick(court: Court, slotIdx: number) {
  const blocks = grid.value[court.id] ?? [];
  const cell = blocks.find((b) => b.slotIdx === slotIdx);
  if (!cell || cell.type !== 'available') return;
  const slot = timeSlots.value[slotIdx];
  if (!slot) return;
  const date = slotStartDate(slot);
  emit('book-slot', { court, date, hour: parseInt(slot.split(':')[0]!) });
}

function handleBookedCellClick(booking: BookingDetail) {
  if (booking.session_type === 'open_play') {
    emit('view-open-play', booking);
    return;
  }

  emit('view-booking', booking);
}
</script>

<template>
  <div
    class="overflow-hidden rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)]"
  >
    <!-- Date nav header -->
    <div
      class="flex items-center justify-between border-b border-[#dbe4ef] bg-white px-4 py-3"
    >
      <button
        type="button"
        class="flex h-8 w-8 items-center justify-center rounded-md transition-colors hover:bg-[#f1f5f9]"
        @click="prevDay"
      >
        <UIcon name="i-heroicons-chevron-left" class="h-5 w-5 text-[#64748b]" />
      </button>

      <AppDatePicker
        variant="nav"
        :model-value="selectedDate"
        :label="headerLabel"
        :allow-past="true"
        @update:model-value="emit('update:selectedDate', $event)"
      />

      <button
        type="button"
        class="flex h-8 w-8 items-center justify-center rounded-md transition-colors hover:bg-[#f1f5f9]"
        @click="nextDay"
      >
        <UIcon
          name="i-heroicons-chevron-right"
          class="h-5 w-5 text-[#64748b]"
        />
      </button>
    </div>

    <!-- Grid: courts = rows (left), time = columns (scrolls both ways) -->
    <div
      ref="scrollWrapper"
      class="w-full overflow-auto"
      style="max-height: 560px"
      @scroll="handleGridScroll"
    >
      <table class="border-collapse" style="min-width: max-content">
        <thead>
          <tr>
            <!-- Corner: sticky top + left -->
            <th
              class="sticky left-0 top-0 z-30 w-36 min-w-[144px] border-b border-r border-[#dbe4ef] bg-white px-3 py-3 text-left text-sm font-medium text-[#64748b]"
            >
              Court
            </th>
            <!-- Time column headers: sticky top -->
            <th
              v-for="slot in timeSlots"
              :key="slot"
              class="sticky top-0 z-20 min-w-[88px] border-b border-r border-[#dbe4ef] bg-white px-2 py-3 text-center text-sm font-medium text-[#64748b] last:border-r-0"
            >
              {{ formatTimeLabel(slot) }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="court in courts"
            :key="court.id"
            class="border-b border-[#dbe4ef] last:border-b-0"
          >
            <!-- Court label: sticky left -->
            <td
              class="sticky left-0 z-10 w-36 min-w-[144px] border-r border-[#dbe4ef] bg-white px-3 py-2"
            >
              <span class="text-sm font-semibold text-[#0f1728]">{{
                court.name
              }}</span>
            </td>

            <!-- Time slot cells -->
            <td
              v-for="block in grid[court.id] || []"
              :key="block.slotIdx"
              :colspan="block.span"
              class="group relative border-r border-[#dbe4ef] p-1.5 last:border-r-0"
            >
              <div
                :style="{ minWidth: `${bookingBlockWidth(block.span)}px` }"
                class="w-full"
              >
                <!-- Closed slot -->
                <div
                  v-if="block.type === 'closed'"
                  class="h-12 rounded-lg bg-[#fee2e2] flex items-center justify-center"
                  :style="{ minWidth: `${bookingBlockWidth(block.span)}px` }"
                >
                  <span
                    class="text-xs font-bold tracking-widest text-[#991b1b] uppercase"
                    >Closed</span
                  >
                </div>

                <!-- Booked slot -->
                <div
                  v-else-if="block.type === 'booked'"
                  class="group relative h-12 cursor-pointer overflow-hidden rounded-lg transition-opacity hover:opacity-90"
                  :class="
                    block.booking!.session_type === 'open_play'
                      ? 'border border-[#7c3aed] bg-[#8b5cf6] text-white'
                      : ''
                  "
                  :style="
                    block.booking!.session_type === 'open_play'
                      ? undefined
                      : {
                          backgroundColor: bookingBg(block.booking!.status)
                        }
                  "
                  @click="handleBookedCellClick(block.booking!)"
                >
                  <div
                    class="absolute inset-y-0 flex min-w-0 flex-col justify-center pr-5"
                    :style="bookingContentStyle(block)"
                  >
                    <p
                      class="truncate text-[11px] font-bold"
                      :style="
                        block.booking!.session_type === 'open_play'
                          ? undefined
                          : {
                              color: bookingTextColor(block.booking!.status)
                            }
                      "
                    >
                      {{ bookingLabel(block.booking!) }}
                    </p>
                    <p
                      class="text-[9px] uppercase tracking-wider opacity-80"
                      :style="
                        block.booking!.session_type === 'open_play'
                          ? undefined
                          : {
                              color: bookingTextColor(block.booking!.status)
                            }
                      "
                    >
                      {{
                        block.booking!.session_type === 'open_play'
                          ? getOpenPlaySession(block.booking!)
                            ? `${getOpenPlaySession(block.booking!)!.participants_count} / ${getOpenPlaySession(block.booking!)!.max_players}`
                            : 'Open Session'
                          : statusLabel(block.booking!.status)
                      }}
                    </p>
                  </div>
                </div>

                <!-- Past slot -->
                <div
                  v-else-if="block.type === 'past'"
                  class="h-12 rounded-lg bg-[#f1f5f9] opacity-50"
                  :style="{ minWidth: `${bookingBlockWidth(block.span)}px` }"
                />

                <!-- Available slot -->
                <div
                  v-else
                  class="relative h-12 rounded-lg border border-dashed border-[#93c5fd] bg-[#dbeafe] transition-all duration-75 group-hover:border-none group-hover:bg-[#bfdbfe]"
                  :style="{ minWidth: `${bookingBlockWidth(block.span)}px` }"
                >
                  <button
                    type="button"
                    class="absolute cursor-pointer inset-0 flex items-center justify-center gap-1 rounded-lg bg-[var(--aktiv-primary)] text-white opacity-0 transition-opacity duration-75 group-hover:opacity-100"
                    @click="handleCellClick(court, block.slotIdx)"
                  >
                    <UIcon name="i-heroicons-plus" class="h-3.5 w-3.5" />
                    <span class="text-xs font-bold">Book</span>
                  </button>
                </div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Legend -->
    <div
      class="flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-[#dbe4ef] bg-[#f8fafc] px-4 py-3"
    >
      <div
        v-for="s in [
          'pending_payment',
          'payment_sent',
          'confirmed'
        ] as BookingStatus[]"
        :key="s"
        class="flex items-center gap-1.5"
      >
        <span
          class="h-3 w-3 rounded-sm"
          :style="{ backgroundColor: bookingBg(s) }"
        />
        <span class="text-xs text-[var(--aktiv-muted)]">{{
          statusLabel(s)
        }}</span>
      </div>
      <div class="flex items-center gap-1.5">
        <span class="h-3 w-3 rounded-sm border border-[#7c3aed] bg-[#8b5cf6]" />
        <span class="text-xs text-[var(--aktiv-muted)]">Open Play</span>
      </div>
      <div class="flex items-center gap-1.5">
        <span
          class="h-3 w-3 rounded-sm bg-[#dbeafe] border border-dashed border-[#93c5fd]"
        />
        <span class="text-xs text-[var(--aktiv-muted)]">Available</span>
      </div>

      <div class="flex items-center gap-1.5">
        <span class="h-3 w-3 rounded-sm bg-[#fee2e2]" />
        <span class="text-xs text-[var(--aktiv-muted)]">Closed</span>
      </div>
      <div class="flex items-center gap-1.5">
        <span
          class="h-3 w-3 rounded-sm bg-[var(--aktiv-border)] opacity-50 border border-[#dbe4ef]"
        />
        <span class="text-xs text-[var(--aktiv-muted)]">Past</span>
      </div>
    </div>
  </div>
</template>
