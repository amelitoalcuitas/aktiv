<script setup lang="ts">
import { CalendarDate } from '@internationalized/date';

const props = withDefaults(
  defineProps<{
    modelValue: Date;
    /** "input" = UInputDate with trailing popover (table filter)
     *  "nav"   = icon + label button with popover (grid header) */
    variant?: 'input' | 'nav';
    /** Visual treatment for nav variant */
    display?: 'compact' | 'field';
    /** Label text shown in nav variant */
    label?: string;
    /** Allow selecting past dates (default true) */
    allowPast?: boolean;
    disabled?: boolean;
  }>(),
  { variant: 'input', display: 'compact', allowPast: true, disabled: false }
);

const emit = defineEmits<{ 'update:modelValue': [Date] }>();

const inputRef = useTemplateRef<any>('inputDate');
const open = ref(false);

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
    open.value = false;
  }
});

const minValue = computed(() => (props.allowPast ? undefined : today));
const navButtonClass = computed(() =>
  props.display === 'field'
    ? 'flex h-8 w-full items-center gap-2 rounded-md border border-[var(--aktiv-border)] bg-white px-3 text-left transition-colors hover:border-[var(--aktiv-primary,#004e89)]'
    : 'flex items-center gap-1.5 rounded-md px-2 py-1 transition-colors hover:bg-[var(--aktiv-border,#f1f5f9)] ring ring-1.5 ring-[var(--aktiv-border)]'
);
</script>

<template>
  <!-- ── Input variant ─────────────────────────────────────── -->
  <UInputDate
    v-if="variant === 'input'"
    ref="inputDate"
    v-model="calendarDate"
    :disabled="props.disabled"
  >
    <template #trailing>
      <UPopover v-model:open="open" :reference="inputRef?.inputsRef[3]?.$el">
        <UButton
          color="neutral"
          variant="link"
          size="sm"
          icon="i-lucide-calendar"
          aria-label="Select a date"
          class="px-0"
          :disabled="props.disabled"
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
  <UPopover v-else v-model:open="open">
    <button
      type="button"
      :disabled="props.disabled"
      :class="[
        navButtonClass,
        props.disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'
      ]"
    >
      <span
        class="min-w-0 truncate text-[var(--aktiv-ink,#0f1728)]"
        :class="
          props.display === 'field'
            ? 'text-sm font-normal'
            : 'text-sm font-semibold'
        "
      >
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
