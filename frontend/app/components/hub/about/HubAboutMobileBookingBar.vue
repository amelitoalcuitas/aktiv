<script setup lang="ts">
import type { Court, Hub, HubEvent } from '~/types/hub';
import type { SelectedSlot } from '~/types/booking';

const props = defineProps<{
  open: boolean;
  selectedSlots: SelectedSlot[];
  courts: Court[];
  hubId: string;
  hub: Hub | null;
  promoEvents: HubEvent[];
}>();

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'scroll-to-schedule'): void;
  (e: 'booking-created'): void;
  (e: 'clear'): void;
  (e: 'remove-slots', slots: SelectedSlot[]): void;
}>();

const isOpen = computed({
  get: () => props.open,
  set: (value: boolean) => emit('update:open', value)
});

const mobileTotalSlots = computed(() => props.selectedSlots.length);

const mobileGrandTotal = computed(() => {
  let total = 0;

  for (const slot of props.selectedSlots) {
    const court = props.courts.find((item) => item.id === slot.courtId);
    if (court) total += parseFloat(court.price_per_hour);
  }

  return total;
});

function handleClear(): void {
  emit('clear');
  isOpen.value = false;
}
</script>

<template>
  <div
    v-if="courts.length > 0"
    class="fixed bottom-0 left-0 right-0 z-40 p-4 xl:hidden"
    style="
      background: var(--aktiv-surface);
      border-top: 1px solid var(--aktiv-border);
      box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.08);
    "
  >
    <button
      v-if="mobileTotalSlots > 0"
      type="button"
      class="flex w-full items-center justify-between gap-3 rounded-2xl bg-[var(--aktiv-primary)] px-5 py-4 text-white shadow-lg active:opacity-90"
      @click="isOpen = true"
    >
      <div class="flex items-center gap-2.5">
        <span
          class="inline-flex items-center justify-center rounded-full bg-white/20 px-2.5 py-0.5 text-sm font-bold"
        >
          {{ mobileTotalSlots }} slot{{ mobileTotalSlots !== 1 ? 's' : '' }}
        </span>
        <span class="text-sm font-semibold">Booking Summary</span>
      </div>
      <div class="flex items-center gap-2">
        <span class="text-lg font-black">
          ₱{{
            mobileGrandTotal.toLocaleString('en-PH', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2
            })
          }}
        </span>
        <UIcon name="i-heroicons-chevron-up" class="h-5 w-5 opacity-70" />
      </div>
    </button>

    <button
      v-else
      type="button"
      class="flex w-full items-center justify-center gap-2 rounded-2xl bg-[var(--aktiv-primary)] px-5 py-4 text-white shadow-lg active:opacity-90"
      @click="emit('scroll-to-schedule')"
    >
      <UIcon name="i-heroicons-calendar-days" class="h-5 w-5" />
      <span class="text-sm font-semibold">Book a Court</span>
    </button>
  </div>

  <UDrawer
    v-model:open="isOpen"
    direction="bottom"
    :ui="{ content: 'max-h-[85dvh]' }"
  >
    <template #content>
      <div class="overflow-y-auto p-4">
        <SchedulerBookingSummary
          :selected-slots="selectedSlots"
          :courts="courts"
          :hub-id="hubId"
          :hub="hub"
          :promo-events="promoEvents"
          @booking-created="emit('booking-created')"
          @clear="handleClear"
          @remove-slots="emit('remove-slots', $event)"
        />
      </div>
    </template>
  </UDrawer>
</template>
