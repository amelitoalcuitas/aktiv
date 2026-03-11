<script setup lang="ts">
import FullCalendar from '@fullcalendar/vue3';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import type { DateClickArg } from '@fullcalendar/interaction';
import type { EventInput, CalendarOptions } from '@fullcalendar/core';

const props = withDefaults(
  defineProps<{
    events?: EventInput[];
    minTime?: string;
    maxTime?: string;
  }>(),
  {
    events: () => [],
    minTime: '06:00:00',
    maxTime: '23:00:00'
  }
);

const emit = defineEmits<{
  'slot-click': [{ date: Date; dateStr: string }];
}>();

function handleDateClick(info: DateClickArg) {
  // Block clicks on past time slots (frontend guard; backend also validates)
  if (info.date <= new Date()) return;
  emit('slot-click', { date: info.date, dateStr: info.dateStr });
}

const calendarOptions = computed<CalendarOptions>(() => ({
  plugins: [timeGridPlugin, interactionPlugin],
  initialView: 'timeGridWeek',
  firstDay: 1,
  headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: 'timeGridDay,timeGridWeek'
  },
  buttonText: {
    today: 'Today',
    day: 'Day',
    week: 'Week'
  },
  slotDuration: '00:30:00',
  slotMinTime: props.minTime,
  slotMaxTime: props.maxTime,
  allDaySlot: false,
  expandRows: false,
  height: 'auto',
  validRange: { start: new Date().toISOString().split('T')[0] },
  dateClick: handleDateClick,
  events: props.events,
  nowIndicator: true,
  eventDisplay: 'block'
}));
</script>

<template>
  <div class="aktiv-calendar">
    <ClientOnly>
      <FullCalendar :options="calendarOptions" />
      <template #fallback>
        <div
          class="flex h-[600px] items-center justify-center text-[var(--aktiv-muted)]"
        >
          <UIcon
            name="i-heroicons-calendar-days"
            class="h-8 w-8 animate-pulse"
          />
        </div>
      </template>
    </ClientOnly>
  </div>
</template>

<style scoped>
/* ── Wrapper ─────────────────────────────────────────────────── */
.aktiv-calendar :deep(.fc) {
  font-family: 'Roboto', 'Segoe UI', sans-serif;
  color: var(--aktiv-ink);
}

/* ── Toolbar ─────────────────────────────────────────────────── */
.aktiv-calendar :deep(.fc .fc-toolbar.fc-header-toolbar) {
  margin-bottom: 1rem;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.aktiv-calendar :deep(.fc .fc-toolbar-title) {
  font-size: 1rem;
  font-weight: 700;
  color: var(--aktiv-ink);
}

/* ── Buttons ─────────────────────────────────────────────────── */
.aktiv-calendar :deep(.fc .fc-button),
.aktiv-calendar :deep(.fc .fc-button-primary) {
  background-color: var(--aktiv-surface);
  border: 1px solid var(--aktiv-border);
  color: var(--aktiv-ink);
  border-radius: 6px;
  font-size: 0.8125rem;
  font-weight: 500;
  padding: 0.3125rem 0.75rem;
  box-shadow: none;
  text-transform: capitalize;
  transition:
    background-color 0.15s,
    border-color 0.15s;
}

.aktiv-calendar :deep(.fc .fc-button:hover),
.aktiv-calendar :deep(.fc .fc-button-primary:not(.fc-button-active):hover) {
  background-color: var(--aktiv-border);
  border-color: var(--aktiv-border);
  color: var(--aktiv-ink);
}

.aktiv-calendar :deep(.fc .fc-button-primary.fc-button-active),
.aktiv-calendar :deep(.fc .fc-button-active) {
  background-color: var(--aktiv-primary) !important;
  border-color: var(--aktiv-primary) !important;
  color: #fff !important;
}

.aktiv-calendar :deep(.fc .fc-button:focus),
.aktiv-calendar :deep(.fc .fc-button-primary:focus) {
  box-shadow: 0 0 0 2px var(--aktiv-primary);
  outline: none;
}

.aktiv-calendar :deep(.fc .fc-button:disabled) {
  opacity: 0.4;
  cursor: default;
}

/* ── Grid borders ────────────────────────────────────────────── */
.aktiv-calendar :deep(.fc-theme-standard .fc-scrollgrid),
.aktiv-calendar :deep(.fc-theme-standard td),
.aktiv-calendar :deep(.fc-theme-standard th) {
  border-color: var(--aktiv-border);
}

/* ── Column headers (Mon, Tue…) ──────────────────────────────── */
.aktiv-calendar :deep(.fc .fc-col-header-cell-cushion) {
  font-weight: 600;
  font-size: 0.8125rem;
  color: var(--aktiv-ink);
  text-decoration: none;
  padding: 0.5rem 0.25rem;
}

.aktiv-calendar
  :deep(.fc .fc-col-header-cell.fc-day-today .fc-col-header-cell-cushion) {
  color: var(--aktiv-primary);
}

/* ── Time labels ─────────────────────────────────────────────── */
.aktiv-calendar :deep(.fc .fc-timegrid-slot-label-cushion) {
  font-size: 0.6875rem;
  color: var(--aktiv-muted);
  padding-right: 0.5rem;
}

/* ── Today highlight ─────────────────────────────────────────── */
.aktiv-calendar :deep(.fc .fc-day-today) {
  background-color: rgba(15, 118, 191, 0.04) !important;
}

/* ── Time slots (clickable) ──────────────────────────────────── */
.aktiv-calendar :deep(.fc .fc-timegrid-slot) {
  cursor: pointer;
}

.aktiv-calendar :deep(.fc .fc-timegrid-slot:hover) {
  background-color: rgba(15, 118, 191, 0.06);
}

/* ── Now indicator ───────────────────────────────────────────── */
.aktiv-calendar :deep(.fc .fc-timegrid-now-indicator-line) {
  border-color: var(--aktiv-primary);
}

.aktiv-calendar :deep(.fc .fc-timegrid-now-indicator-arrow) {
  border-top-color: var(--aktiv-primary);
  border-bottom-color: var(--aktiv-primary);
}

/* ── Events ──────────────────────────────────────────────────── */
.aktiv-calendar :deep(.fc .fc-event) {
  border-radius: 6px;
  border: none;
  font-size: 0.75rem;
  font-weight: 500;
}
</style>
