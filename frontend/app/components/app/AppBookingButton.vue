<script setup lang="ts">
import { useUserBookingStore } from '~/stores/booking';

const bookingStore = useUserBookingStore();
const isOpen = ref(false);
</script>

<template>
  <div class="relative">
    <UPopover
      v-model:open="isOpen"
      :content="{
        align: 'end',
        side: 'bottom',
        sideOffset: 8
      }"
    >
      <button
        class="relative flex h-9 w-9 items-center justify-center rounded-full text-[#3a4a5c] transition hover:bg-[#f0f4f8] hover:text-[#004e89]"
        aria-label="My Bookings"
      >
        <UIcon name="i-heroicons-calendar-days" class="h-5 w-5" />
        <UBadge
          v-if="bookingStore.pendingCount > 0"
          color="secondary"
          variant="solid"
          size="xs"
          class="absolute -right-0.5 -top-0.5 min-w-[18px] justify-center px-1"
        >
          {{ bookingStore.pendingCount }}
        </UBadge>
      </button>

      <template #content>
        <AppBookingPopover @close="isOpen = false" />
      </template>
    </UPopover>
  </div>
</template>
