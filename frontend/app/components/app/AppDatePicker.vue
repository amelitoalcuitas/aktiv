<script setup lang="ts">
import { CalendarDate } from '@internationalized/date';

const props = withDefaults(
  defineProps<{
    modelValue: Date;
    /** "input" = UInputDate with trailing popover (table filter)
     *  "nav"   = icon + label button with popover (grid header) */
    variant?: 'input' | 'nav';
    /** Label text shown in nav variant */
    label?: string;
    /** Allow selecting past dates (default true) */
    allowPast?: boolean;
  }>(),
  { variant: 'input', allowPast: true }
);

const emit = defineEmits<{ 'update:modelValue': [Date] }>();

const inputRef = useTemplateRef<any>('inputDate');

const today = (() => {
  const d = new Date();
  return new CalendarDate(d.getFullYear(), d.getMonth() + 1, d.getDate());
})();

const calendarDate = computed({
  get() {
    const d = props.modelValue;
    return new CalendarDate(d.getFullYear(), d.getMonth() + 1, d.getDate());
  },
  set(val: CalendarDate) {
    emit('update:modelValue', new Date(val.year, val.month - 1, val.day));
  }
});

const minValue = computed(() => (props.allowPast ? undefined : today));
</script>

<template>
  <!-- ── Input variant ─────────────────────────────────────── -->
  <UInputDate v-if="variant === 'input'" ref="inputDate" v-model="calendarDate">
    <template #trailing>
      <UPopover :reference="inputRef?.inputsRef[3]?.$el">
        <UButton
          color="neutral"
          variant="link"
          size="sm"
          icon="i-lucide-calendar"
          aria-label="Select a date"
          class="px-0"
        />
        <template #content>
          <UCalendar
            v-model="calendarDate"
            color="secondary"
            :min-value="minValue"
            class="p-2"
          />
        </template>
      </UPopover>
    </template>
  </UInputDate>

  <!-- ── Nav variant ───────────────────────────────────────── -->
  <UPopover v-else>
    <button
      type="button"
      class="flex items-center gap-1.5 rounded-md px-2 py-1 transition-colors hover:bg-[var(--aktiv-border,#f1f5f9)]"
    >
      <UIcon
        name="i-heroicons-calendar-days"
        class="h-4 w-4 text-[var(--aktiv-muted,#64748b)]"
      />
      <span class="text-sm font-semibold text-[var(--aktiv-ink,#0f1728)]">
        {{ label }}
      </span>
    </button>
    <template #content>
      <UCalendar
        v-model="calendarDate"
        color="secondary"
        :min-value="minValue"
        class="p-2"
      />
    </template>
  </UPopover>
</template>
