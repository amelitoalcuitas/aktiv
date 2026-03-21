<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    modelValue: Date;
  }>(),
  {}
);

const emit = defineEmits<{
  'update:modelValue': [Date];
}>();

const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const MONTHS = [
  'January',
  'February',
  'March',
  'April',
  'May',
  'June',
  'July',
  'August',
  'September',
  'October',
  'November',
  'December'
];

const today = new Date();
today.setHours(0, 0, 0, 0);

const viewYear = ref(props.modelValue.getFullYear());
const viewMonth = ref(props.modelValue.getMonth());

interface DayCell {
  date: Date;
  day: number;
  isCurrentMonth: boolean;
  isToday: boolean;
  isSelected: boolean;
  isPast: boolean;
}

function sameDay(a: Date, b: Date): boolean {
  return (
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate()
  );
}

const cells = computed<DayCell[]>(() => {
  const firstDay = new Date(viewYear.value, viewMonth.value, 1);
  const dow = firstDay.getDay(); // 0=Sun … 6=Sat
  const offset = dow === 0 ? 6 : dow - 1; // shift to Monday-anchor

  const selected = new Date(props.modelValue);
  selected.setHours(0, 0, 0, 0);

  return Array.from({ length: 42 }, (_, i) => {
    const d = new Date(viewYear.value, viewMonth.value, 1 - offset + i);
    d.setHours(0, 0, 0, 0);
    return {
      date: d,
      day: d.getDate(),
      isCurrentMonth: d.getMonth() === viewMonth.value,
      isToday: sameDay(d, today),
      isSelected: sameDay(d, selected),
      isPast: d < today
    };
  });
});

const canGoPrev = computed(() => {
  return (
    viewYear.value > today.getFullYear() ||
    (viewYear.value === today.getFullYear() &&
      viewMonth.value > today.getMonth())
  );
});

function prevMonth() {
  if (!canGoPrev.value) return;
  if (viewMonth.value === 0) {
    viewMonth.value = 11;
    viewYear.value--;
  } else {
    viewMonth.value--;
  }
}

function nextMonth() {
  if (viewMonth.value === 11) {
    viewMonth.value = 0;
    viewYear.value++;
  } else {
    viewMonth.value++;
  }
}

function selectDay(cell: DayCell) {
  if (cell.isPast) return;
  emit('update:modelValue', new Date(cell.date));
}

// Keep view in sync when parent changes the date (e.g. resource grid prev/next)
watch(
  () => props.modelValue,
  (val) => {
    viewYear.value = val.getFullYear();
    viewMonth.value = val.getMonth();
  }
);
</script>

<template>
  <div
    class="rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-4"
  >
    <!-- Month navigation -->
    <div class="mb-3 flex items-center justify-between">
      <button
        type="button"
        :disabled="!canGoPrev"
        class="flex h-8 w-8 items-center justify-center rounded-md transition-colors hover:bg-[var(--aktiv-border)] disabled:cursor-default disabled:opacity-30"
        @click="prevMonth"
      >
        <UIcon name="i-heroicons-chevron-left" class="h-4 w-4" />
      </button>
      <span class="text-sm font-semibold text-[var(--aktiv-ink)]">
        {{ MONTHS[viewMonth] }} {{ viewYear }}
      </span>
      <button
        type="button"
        class="flex h-8 w-8 items-center justify-center rounded-md transition-colors hover:bg-[var(--aktiv-border)]"
        @click="nextMonth"
      >
        <UIcon name="i-heroicons-chevron-right" class="h-4 w-4" />
      </button>
    </div>

    <!-- Weekday headers -->
    <div class="mb-1 grid grid-cols-7">
      <div
        v-for="wd in WEEKDAYS"
        :key="wd"
        class="py-1 text-center text-xs font-medium text-[var(--aktiv-muted)]"
      >
        {{ wd }}
      </div>
    </div>

    <!-- Day cells -->
    <div class="grid grid-cols-7 gap-y-0.5">
      <button
        v-for="(cell, i) in cells"
        :key="i"
        type="button"
        :disabled="cell.isPast"
        :class="[
          'flex h-9 w-full items-center justify-center rounded-md text-sm transition-colors',
          cell.isSelected
            ? 'bg-[var(--aktiv-primary)] font-semibold text-white'
            : cell.isToday
              ? 'border border-[var(--aktiv-primary)] font-bold text-[var(--aktiv-primary)]'
              : cell.isPast
                ? 'cursor-default opacity-30 text-[var(--aktiv-ink)]'
                : cell.isCurrentMonth
                  ? 'text-[var(--aktiv-ink)] hover:bg-[var(--aktiv-border)]'
                  : 'text-[var(--aktiv-muted)] hover:bg-[var(--aktiv-border)]'
        ]"
        @click="selectDay(cell)"
      >
        {{ cell.day }}
      </button>
    </div>
  </div>
</template>
