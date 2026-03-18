<script setup lang="ts">
import type { Court } from '~/types/hub';
import type { BookingDetail, BookingStatus } from '~/types/booking';

const props = withDefaults(
  defineProps<{
    courts: Court[];
    bookings: BookingDetail[];
    selectedDate: Date;
    minTime?: string;
    maxTime?: string;
  }>(),
  {
    minTime: '06:00',
    maxTime: '23:00'
  }
);

const emit = defineEmits<{
  'book-slot': [{ court: Court; date: Date; hour: number }];
  'update:selectedDate': [Date];
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
  booking: BookingDetail | null;
}

const now = ref(new Date());
let nowTimer: ReturnType<typeof setInterval>;
onMounted(() => {
  nowTimer = setInterval(() => {
    now.value = new Date();
  }, 60_000);
});
onUnmounted(() => clearInterval(nowTimer));

const grid = computed(() => {
  const result: Record<number, CellState[]> = {};
  for (const court of props.courts) {
    const courtBookings = props.bookings.filter((b) => b.court_id === court.id);
    result[court.id] = timeSlots.value.map((slot) => {
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
      if (start <= now.value) return { type: 'past', booking: null };
      return { type: 'available', booking: null };
    });
  }
  return result;
});

// ── Booking display ────────────────────────────────────────────
function bookingLabel(booking: BookingDetail): string {
  if (booking.booked_by_user) return booking.booked_by_user.name;
  if (booking.guest_name) return booking.guest_name;
  return 'Booked';
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
      return '#854d0e'; // Yellow 800
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

// ── Native date-picker (same as SchedulerResourceGrid) ─────────
const dateInput = useTemplateRef<HTMLInputElement>('dateInput');

const todayStr = computed(() => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
});

const selectedDateStr = computed(() => {
  const d = props.selectedDate;
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
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

// ── Auto-scroll ───────────────────────────────────────────────
const scrollWrapper = useTemplateRef<HTMLDivElement>('scrollWrapper');
function scrollToCurrentTime() {
  if (!scrollWrapper.value) return;
  const nowH = new Date().getHours();
  const idx = timeSlots.value.findIndex((slot) => {
    const h = parseInt(slot.split(':')[0] ?? '0', 10);
    return h >= nowH;
  });
  scrollWrapper.value.scrollLeft = Math.max(0, (idx - 1) * 88);
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
      else scrollWrapper.value.scrollLeft = 0;
    })
);

// ── Slot click ─────────────────────────────────────────────────
function handleCellClick(court: Court, slotIdx: number) {
  const cell = grid.value[court.id]?.[slotIdx];
  if (!cell || cell.type !== 'available') return;
  const slot = timeSlots.value[slotIdx];
  if (!slot) return;
  const date = slotStartDate(slot);
  emit('book-slot', { court, date, hour: parseInt(slot.split(':')[0]!) });
}
</script>

<template>
  <div
    class="w-full min-w-0 rounded-2xl border border-[#dbe4ef] bg-white shadow-sm"
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
          class="flex items-center gap-1.5 rounded-md px-2 py-1 transition-colors hover:bg-[#f1f5f9]"
          @click="openDatePicker"
        >
          <UIcon
            name="i-heroicons-calendar-days"
            class="h-4 w-4 text-[#64748b]"
          />
          <span class="text-sm font-semibold text-[#0f1728]">
            {{ headerLabel }}
          </span>
        </button>
      </div>

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
              v-for="(slot, slotIdx) in timeSlots"
              :key="slot"
              class="group relative min-w-[88px] border-r border-[#dbe4ef] p-1.5 last:border-r-0"
            >
              <!-- Booked slot -->
              <div
                v-if="grid[court.id]?.[slotIdx].type === 'booked'"
                class="flex h-12 flex-col justify-center rounded-lg px-2 shadow-sm"
                :style="{
                  backgroundColor: bookingBg(
                    grid[court.id]![slotIdx].booking!.status
                  )
                }"
              >
                <p
                  class="truncate text-[11px] font-bold"
                  :style="{
                    color: bookingTextColor(
                      grid[court.id]![slotIdx].booking!.status
                    )
                  }"
                >
                  {{ bookingLabel(grid[court.id]![slotIdx].booking!) }}
                </p>
                <p
                  class="text-[9px] uppercase tracking-wider opacity-80"
                  :style="{
                    color: bookingTextColor(
                      grid[court.id]![slotIdx].booking!.status
                    )
                  }"
                >
                  {{ statusLabel(grid[court.id]![slotIdx].booking!.status) }}
                </p>
              </div>

              <!-- Past slot -->
              <div
                v-else-if="grid[court.id]?.[slotIdx].type === 'past'"
                class="h-12 rounded-lg bg-[#f1f5f9] opacity-50"
              />

              <!-- Available slot -->
              <div
                v-else
                class="relative h-12 rounded-lg border border-dashed border-[#93c5fd] bg-[#dbeafe] transition-all duration-75 group-hover:border-[#3b82f6] group-hover:bg-[#bfdbfe]"
              >


                <button
                  type="button"
                  class="absolute inset-0 flex items-center justify-center gap-1 rounded-lg bg-[#004e89] text-white opacity-0 transition-opacity duration-75 group-hover:opacity-100"
                  @click="handleCellClick(court, slotIdx)"
                >
                  <UIcon name="i-heroicons-plus" class="h-3.5 w-3.5" />

                  <span class="text-xs font-bold">Book</span>
                </button>
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
        <span class="text-xs text-[#64748b]">{{ statusLabel(s) }}</span>
      </div>
      <div class="flex items-center gap-1.5">
        <span
          class="h-3 w-3 rounded-sm bg-[#dbeafe] border border-dashed border-[#93c5fd]"
        />
        <span class="text-xs text-[#64748b]">Available</span>
      </div>

      <div class="flex items-center gap-1.5">

        <span
          class="h-3 w-3 rounded-sm bg-[#f1f5f9] opacity-50 border border-[#dbe4ef]"
        />
        <span class="text-xs text-[#64748b]">Past / Unavailable</span>
      </div>
    </div>
  </div>
</template>
