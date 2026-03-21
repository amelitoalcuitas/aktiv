<script setup lang="ts">
import type { BookingStatus, UserBooking } from '~/types/booking';
import { useUserBookingStore } from '~/stores/booking';

defineEmits<{ close: [] }>();

const bookingStore = useUserBookingStore();

type DisplayStatus = BookingStatus | 'expired';

const statusConfig: Record<DisplayStatus, { label: string; color: 'warning' | 'info' | 'success' | 'error' | 'neutral' }> = {
  pending_payment: { label: 'Pending Payment', color: 'warning' },
  payment_sent:    { label: 'Payment Sent',    color: 'info' },
  confirmed:       { label: 'Confirmed',       color: 'success' },
  cancelled:       { label: 'Cancelled',       color: 'error' },
  completed:       { label: 'Completed',       color: 'neutral' },
  expired:         { label: 'Expired',         color: 'neutral' },
};

function effectiveStatus(booking: UserBooking): DisplayStatus {
  if (booking.status === 'cancelled' && booking.cancelled_by === 'system') return 'expired';
  return booking.status;
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('en-PH', {
    timeZone: 'Asia/Manila',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

function formatTime(start: string, end: string): string {
  const opts: Intl.DateTimeFormatOptions = {
    timeZone: 'Asia/Manila',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  };
  const s = new Date(start).toLocaleTimeString('en-PH', opts);
  const e = new Date(end).toLocaleTimeString('en-PH', opts);
  return `${s} – ${e}`;
}
</script>

<template>
  <div class="flex w-80 flex-col">
    <!-- Header -->
    <div class="flex items-center justify-between border-b border-[#dbe4ef] px-4 py-3">
      <span class="text-sm font-semibold text-[#0f1728]">My Bookings</span>
      <NuxtLink
        to="/bookings"
        class="text-xs text-[#004e89] hover:underline"
      >
        View all
      </NuxtLink>
    </div>

    <!-- List -->
    <div class="max-h-[420px] overflow-y-auto">
      <template v-if="bookingStore.recentBookings.length > 0">
        <NuxtLink
          v-for="booking in bookingStore.recentBookings"
          :key="booking.id"
          :to="`/bookings?bookingId=${booking.id}`"
          class="flex items-start justify-between gap-3 border-b border-[#dbe4ef] px-4 py-3 last:border-0 hover:bg-[#f8fbff] transition-colors"
        >
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium text-[#0f1728]">
              {{ booking.court?.hub?.name ?? '—' }}
            </p>
            <p class="truncate text-xs text-[#64748b]">
              {{ booking.court?.name ?? '—' }}
            </p>
            <p class="mt-0.5 text-xs text-[#64748b]">
              {{ formatDate(booking.start_time) }} · {{ formatTime(booking.start_time, booking.end_time) }}
            </p>
          </div>
          <UBadge
            :color="statusConfig[effectiveStatus(booking)]?.color ?? 'neutral'"
            variant="subtle"
            size="xs"
            class="mt-0.5 shrink-0"
          >
            {{ statusConfig[effectiveStatus(booking)]?.label ?? booking.status }}
          </UBadge>
        </NuxtLink>
      </template>

      <div v-else class="px-4 py-8 text-center text-sm text-[#64748b]">
        No bookings yet.
      </div>
    </div>

    <!-- Footer -->
    <div class="border-t border-[#dbe4ef] px-4 py-2.5 text-center text-xs text-[#64748b]">
      <NuxtLink to="/bookings" class="font-medium text-[#004e89] hover:underline">
        View all bookings
      </NuxtLink>
    </div>
  </div>
</template>
