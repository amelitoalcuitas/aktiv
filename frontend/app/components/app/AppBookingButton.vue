<script setup lang="ts">
import { useUserBookingStore } from '~/stores/booking';

const bookingStore = useUserBookingStore();
const route = useRoute();
</script>

<template>
  <UButton
    to="/bookings"
    variant="ghost"
    :color="route.path === '/bookings' ? 'primary' : 'neutral'"
    :class="[
      'inline-flex h-11 items-center gap-2 rounded-none border-b-4 border-transparent px-4',
      route.path === '/bookings'
        ? 'border-[#0f76bf] bg-[#e8f0f8] font-semibold'
        : 'hover:bg-[#f0f4f8]'
    ]"
    aria-label="My Bookings"
  >
    <span>My Bookings</span>
    <UBadge
      v-if="bookingStore.pendingCount > 0"
      color="secondary"
      variant="solid"
      size="xs"
      class="min-w-[18px] justify-center px-1"
    >
      {{ bookingStore.pendingCount }}
    </UBadge>
  </UButton>
</template>
