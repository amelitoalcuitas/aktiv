<script setup lang="ts">
interface DashboardMonthCalendarItem {
  id: string;
  kind: 'event' | 'open_play';
  hubId: string;
  hubName: string;
  title: string;
  date: string;
  timeLabel?: string;
  to: string;
}

const props = withDefaults(
  defineProps<{
    items: DashboardMonthCalendarItem[];
    initialMonth?: string;
    emptyLabel?: string;
    loading?: boolean;
  }>(),
  {
    initialMonth: undefined,
    emptyLabel: 'No events or open play sessions this month.',
    loading: false
  }
);

const emit = defineEmits<{
  'month-change': [month: string];
  'item-click': [item: DashboardMonthCalendarItem];
}>();

const WEEKDAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const MAX_VISIBLE_ITEMS = 3;
const MANILA_TIME_ZONE = 'Asia/Manila';

function getManilaTodayKey(): string {
  return new Intl.DateTimeFormat('en-CA', {
    timeZone: MANILA_TIME_ZONE,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  }).format(new Date());
}

function getInitialMonthKey(initialMonth?: string): string {
  if (initialMonth) return initialMonth.slice(0, 7);
  return getManilaTodayKey().slice(0, 7);
}

function parseDateKey(dateKey: string): Date {
  const [year, month, day] = dateKey.split('-').map(Number);
  return new Date(year, (month ?? 1) - 1, day ?? 1);
}

function toDateKey(date: Date): string {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function addDays(date: Date, days: number): Date {
  const next = new Date(date);
  next.setDate(next.getDate() + days);
  return next;
}

function sortItems(items: DashboardMonthCalendarItem[]) {
  return [...items].sort((left, right) => {
    if (left.kind !== right.kind) {
      return left.kind === 'event' ? -1 : 1;
    }

    const leftTime = left.timeLabel ?? '';
    const rightTime = right.timeLabel ?? '';

    return (
      leftTime.localeCompare(rightTime) ||
      left.title.localeCompare(right.title) ||
      left.hubName.localeCompare(right.hubName)
    );
  });
}

const todayKey = getManilaTodayKey();
const visibleMonthKey = ref(getInitialMonthKey(props.initialMonth));
const currentMonthKey = todayKey.slice(0, 7);
const selectedDateKey = ref('');
const dayButtonRefs = ref<Record<string, HTMLButtonElement | null>>({});

watch(
  () => props.initialMonth,
  (value) => {
    visibleMonthKey.value = getInitialMonthKey(value);
  }
);

watch(
  visibleMonthKey,
  (value) => {
    emit('month-change', `${value}-01`);
  }
);

const visibleMonthDate = computed(() => parseDateKey(`${visibleMonthKey.value}-01`));

const monthLabel = computed(() =>
  new Intl.DateTimeFormat('en-PH', {
    month: 'long',
    year: 'numeric'
  }).format(visibleMonthDate.value)
);

const monthDays = computed(() => {
  const monthStart = visibleMonthDate.value;
  const monthEnd = new Date(
    monthStart.getFullYear(),
    monthStart.getMonth() + 1,
    0
  );
  const gridStart = addDays(monthStart, -monthStart.getDay());
  const gridEnd = addDays(monthEnd, 6 - monthEnd.getDay());
  const days: Array<{
    dateKey: string;
    dayNumber: number;
    isCurrentMonth: boolean;
    isToday: boolean;
  }> = [];

  for (
    let cursor = gridStart;
    cursor <= gridEnd;
    cursor = addDays(cursor, 1)
  ) {
    const dateKey = toDateKey(cursor);
    days.push({
      dateKey,
      dayNumber: cursor.getDate(),
      isCurrentMonth: cursor.getMonth() === monthStart.getMonth(),
      isToday: dateKey === todayKey
    });
  }

  return days;
});

const currentMonthDays = computed(() =>
  monthDays.value.filter((day) => day.isCurrentMonth)
);

const itemsByDate = computed(() => {
  const map = new Map<string, DashboardMonthCalendarItem[]>();

  for (const item of props.items) {
    const existing = map.get(item.date) ?? [];
    existing.push(item);
    map.set(item.date, existing);
  }

  for (const [dateKey, items] of map.entries()) {
    map.set(dateKey, sortItems(items));
  }

  return map;
});

const visibleMonthItemCount = computed(
  () =>
    props.items.filter((item) => item.date.startsWith(visibleMonthKey.value))
      .length
);

const isCurrentMonth = computed(
  () => visibleMonthKey.value === currentMonthKey
);

const selectedDateItems = computed(() => itemsForDate(selectedDateKey.value));

const selectedDateLabel = computed(() => {
  if (!selectedDateKey.value) return monthLabel.value;

  return new Intl.DateTimeFormat('en-PH', {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    year: 'numeric'
  }).format(parseDateKey(selectedDateKey.value));
});

const hasSelectedDateItems = computed(() => selectedDateItems.value.length > 0);

function itemsForDate(dateKey: string): DashboardMonthCalendarItem[] {
  return itemsByDate.value.get(dateKey) ?? [];
}

function resolveSelectedDateKey(): string {
  if (
    isCurrentMonth.value &&
    currentMonthDays.value.some((day) => day.dateKey === todayKey)
  ) {
    return todayKey;
  }

  const firstDateWithItems = currentMonthDays.value.find(
    (day) => itemsForDate(day.dateKey).length > 0
  );

  if (firstDateWithItems) return firstDateWithItems.dateKey;

  return currentMonthDays.value[0]?.dateKey ?? `${visibleMonthKey.value}-01`;
}

function selectDate(dateKey: string) {
  selectedDateKey.value = dateKey;
}

function setDayButtonRef(dateKey: string) {
  return (el: Element | null) => {
    dayButtonRefs.value[dateKey] = el as HTMLButtonElement | null;
  };
}

function scrollToDate(dateKey: string, behavior: ScrollBehavior = 'smooth') {
  nextTick(() => {
    dayButtonRefs.value[dateKey]?.scrollIntoView({
      behavior,
      block: 'nearest',
      inline: 'center'
    });
  });
}

function dayButtonClasses(day: {
  dateKey: string;
  isToday: boolean;
}): string[] {
  const isSelected = day.dateKey === selectedDateKey.value;

  if (isSelected) {
    return ['border-[#004e89] bg-[#004e89] text-white'];
  }

  if (day.isToday) {
    return ['border-[#93c5fd] bg-[#eff6ff] text-[#004e89]'];
  }

  return ['border-[#dbe4ef] bg-white text-[#0f1728]'];
}

watch(
  [visibleMonthKey, () => props.items],
  () => {
    selectedDateKey.value = resolveSelectedDateKey();
  },
  { immediate: true }
);

function previousMonth() {
  const current = visibleMonthDate.value;
  visibleMonthKey.value = toDateKey(
    new Date(current.getFullYear(), current.getMonth() - 1, 1)
  ).slice(0, 7);
}

function nextMonth() {
  const current = visibleMonthDate.value;
  visibleMonthKey.value = toDateKey(
    new Date(current.getFullYear(), current.getMonth() + 1, 1)
  ).slice(0, 7);
}

function resetToCurrentMonth() {
  visibleMonthKey.value = getManilaTodayKey().slice(0, 7);
  selectedDateKey.value = todayKey;
  scrollToDate(todayKey);
}

function itemLabel(item: DashboardMonthCalendarItem): string {
  return item.timeLabel ? `${item.timeLabel} ${item.title}` : item.title;
}

function itemClasses(item: DashboardMonthCalendarItem): string[] {
  if (item.kind === 'event') {
    return [
      'border-[#bfdbfe] bg-[#eff6ff] text-[#004e89] hover:bg-[#dbeafe]'
    ];
  }

  return ['border-[#99f6e4] bg-[#ecfdf5] text-[#0f766e] hover:bg-[#ccfbf1]'];
}

function onItemClick(item: DashboardMonthCalendarItem) {
  emit('item-click', item);
}
</script>

<template>
  <div class="rounded-2xl border border-[#dbe4ef] bg-white">
    <div
      class="flex flex-col gap-4 border-b border-[#dbe4ef] px-5 py-4 md:flex-row md:items-center md:justify-between"
    >
      <div>
        <h2 class="text-sm font-semibold text-[#0f1728]">Events Calendar</h2>
        <p class="mt-1 text-sm text-[#64748b]">
          Events and open play across all your hubs.
        </p>
      </div>

      <div class="ml-auto flex flex-wrap items-center justify-end gap-2">
        <UButton
          color="neutral"
          variant="ghost"
          icon="i-heroicons-chevron-left"
          aria-label="Previous month"
          @click="previousMonth"
        />
        <UButton
          :color="isCurrentMonth ? 'primary' : 'neutral'"
          :variant="isCurrentMonth ? 'solid' : 'outline'"
          @click="resetToCurrentMonth"
        >
          Today
        </UButton>
        <UButton
          color="neutral"
          variant="ghost"
          icon="i-heroicons-chevron-right"
          aria-label="Next month"
          @click="nextMonth"
        />
      </div>
    </div>

    <div class="border-b border-[#dbe4ef] px-5 py-3">
      <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
        <p class="text-lg font-semibold text-[#0f1728]">
          {{ monthLabel }}
        </p>
        <p class="text-sm text-[#64748b]">
          {{ visibleMonthItemCount }}
          {{ visibleMonthItemCount === 1 ? 'item' : 'items' }}
        </p>
      </div>
    </div>

    <div
      v-if="loading"
      class="hidden border-b border-[#dbe4ef] px-5 py-4 text-sm text-[#64748b] md:block"
    >
      Loading calendar items...
    </div>

    <div
      v-else-if="visibleMonthItemCount === 0"
      class="hidden border-b border-[#dbe4ef] px-5 py-4 text-sm text-[#64748b] md:block"
    >
      {{ emptyLabel }}
    </div>

    <div class="border-b border-[#dbe4ef] px-4 py-4 md:hidden">
      <div class="mb-3 flex gap-2 overflow-x-auto pb-1">
        <button
          v-for="day in currentMonthDays"
          :key="day.dateKey"
          :ref="setDayButtonRef(day.dateKey)"
          type="button"
          :class="[
            'flex min-w-[3.5rem] flex-shrink-0 flex-col items-center rounded-xl border px-2 py-2 text-center transition',
            dayButtonClasses(day)
          ]"
          @click="selectDate(day.dateKey)"
        >
          <span class="text-[11px] font-medium uppercase tracking-wide opacity-80">
            {{
              new Intl.DateTimeFormat('en-PH', { weekday: 'short' }).format(
                parseDateKey(day.dateKey)
              )
            }}
          </span>
          <span class="mt-1 text-base font-semibold">
            {{ day.dayNumber }}
          </span>
          <span
            class="mt-1 h-1.5 w-1.5 rounded-full"
            :class="
              itemsForDate(day.dateKey).length
                ? day.dateKey === selectedDateKey
                  ? 'bg-white'
                  : 'bg-[#0f76bf]'
                : 'bg-transparent'
            "
          />
        </button>
      </div>

      <div class="rounded-xl border border-[#dbe4ef] bg-[#f8fafc] p-3">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="text-sm font-semibold text-[#0f1728]">
              {{ selectedDateLabel }}
            </p>
            <p class="mt-1 text-xs text-[#64748b]">
              {{
                hasSelectedDateItems
                  ? `${selectedDateItems.length} ${selectedDateItems.length === 1 ? 'item' : 'items'}`
                  : 'No events or open play scheduled.'
              }}
            </p>
          </div>

          <span
            v-if="selectedDateKey === todayKey"
            class="rounded-full bg-[#eff6ff] px-2 py-1 text-[11px] font-medium text-[#004e89]"
          >
            Today
          </span>
        </div>

        <div v-if="loading" class="mt-3 text-sm text-[#64748b]">
          Loading calendar items...
        </div>

        <div
          v-else-if="!selectedDateItems.length"
          class="mt-3 rounded-lg border border-dashed border-[#dbe4ef] bg-white px-3 py-4 text-sm text-[#64748b]"
        >
          No events or open play for this date.
        </div>

        <div v-else class="mt-3 space-y-2">
          <button
            v-for="item in selectedDateItems"
            :key="item.id"
            type="button"
            :class="[
              'block w-full rounded-xl border px-3 py-3 text-left transition',
              itemClasses(item)
            ]"
            @click="onItemClick(item)"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-sm font-semibold leading-tight">
                  {{ item.title }}
                </p>
                <p
                  v-if="item.timeLabel"
                  class="mt-1 text-xs font-medium opacity-90"
                >
                  {{ item.timeLabel }}
                </p>
                <p class="mt-1 text-xs opacity-75">
                  {{ item.hubName }}
                </p>
              </div>

              <UIcon
                name="i-heroicons-chevron-right"
                class="mt-0.5 h-4 w-4 flex-shrink-0 opacity-70"
              />
            </div>
          </button>
        </div>
      </div>
    </div>

    <div class="hidden overflow-x-auto md:block">
      <div class="min-w-[980px]">
        <div class="grid grid-cols-7 border-b border-[#dbe4ef]">
          <div
            v-for="label in WEEKDAY_LABELS"
            :key="label"
            class="border-r border-[#dbe4ef] px-3 py-2 text-center text-sm font-medium text-[#64748b] last:border-r-0"
          >
            {{ label }}
          </div>
        </div>

        <div class="grid grid-cols-7">
          <div
            v-for="day in monthDays"
            :key="day.dateKey"
            :class="[
              'min-h-36 border-r border-b border-[#dbe4ef] px-2 py-2 last:border-r-0',
              day.isCurrentMonth ? 'bg-white' : 'bg-[#f8fafc]'
            ]"
          >
            <div class="mb-2 flex items-center justify-between gap-2">
              <span
                :class="[
                  'inline-flex h-7 min-w-7 items-center justify-center rounded-full px-2 text-sm font-medium',
                  day.isToday
                    ? 'bg-[#004e89] text-white'
                    : day.isCurrentMonth
                      ? 'text-[#0f1728]'
                      : 'text-[#94a3b8]'
                ]"
              >
                {{ day.dayNumber }}
              </span>
            </div>

            <div class="space-y-1.5">
              <button
                v-for="item in itemsForDate(day.dateKey).slice(0, MAX_VISIBLE_ITEMS)"
                :key="item.id"
                type="button"
                :title="`${item.hubName} · ${itemLabel(item)}`"
                :class="[
                  'block w-full rounded-md border px-2 py-1 text-left text-xs font-medium leading-tight transition',
                  itemClasses(item)
                ]"
                @click="onItemClick(item)"
              >
                <span class="line-clamp-2">
                  {{ itemLabel(item) }}
                </span>
              </button>

              <p
                v-if="itemsForDate(day.dateKey).length > MAX_VISIBLE_ITEMS"
                class="px-1 text-xs font-medium text-[#64748b]"
              >
                +{{ itemsForDate(day.dateKey).length - MAX_VISIBLE_ITEMS }} more
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
