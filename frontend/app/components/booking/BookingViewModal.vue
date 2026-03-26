<script setup lang="ts">
import type { BookingDetail, BookingStatus } from '~/types/booking';

const props = defineProps<{
  open: boolean;
  booking: BookingDetail | null;
}>();

const emit = defineEmits<{
  'update:open': [boolean];
}>();

const isOpen = computed({
  get: () => props.open,
  set: (val) => emit('update:open', val)
});

function customerLabel(b: BookingDetail | null): string {
  if (!b) return 'Unknown';
  if (b.booked_by_user) return b.booked_by_user.name;
  if (b.guest_name) return `${b.guest_name} (guest)`;
  return 'Unknown';
}

function statusColor(
  status?: BookingStatus
): 'warning' | 'success' | 'error' | 'neutral' | 'primary' {
  switch (status) {
    case 'pending_payment':
      return 'warning';
    case 'payment_sent':
      return 'primary';
    case 'confirmed':
      return 'success';
    case 'cancelled':
      return 'error';
    case 'completed':
      return 'neutral';
    default:
      return 'neutral';
  }
}

function paymentMethodLabel(method: string | null | undefined): string {
  switch (method) {
    case 'gcash': return 'GCash';
    case 'bank_transfer': return 'Bank Transfer';
    case 'pay_on_site': return 'Pay on Site';
    default: return method ?? '—';
  }
}

function statusLabel(status?: BookingStatus): string {
  switch (status) {
    case 'pending_payment':
      return 'Pending Payment';
    case 'payment_sent':
      return 'Receipt Sent';
    case 'confirmed':
      return 'Confirmed';
    case 'cancelled':
      return 'Cancelled';
    case 'completed':
      return 'Completed';
    default:
      return status ?? '';
  }
}

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString('en-PH', {
    timeZone: 'Asia/Manila',
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  });
}

function formatTime(iso: string) {
  return new Date(iso).toLocaleTimeString('en-PH', {
    timeZone: 'Asia/Manila',
    hour: 'numeric',
    minute: '2-digit'
  });
}
</script>

<template>
  <AppModal v-model:open="isOpen" title="Booking Details">
    <template #body>
      <div v-if="booking" class="space-y-4">
        <!-- Read-only status info -->
        <div
          class="rounded-xl border border-[#dbe4ef] bg-[#f8fafc] p-4 text-sm space-y-2"
        >
          <div class="flex items-center justify-between">
            <span class="text-[#64748b]">Status</span>
            <UBadge
              :label="statusLabel(booking.status)"
              :color="statusColor(booking.status)"
              variant="subtle"
            />
          </div>
          <div class="flex items-center justify-between">
            <span class="text-[#64748b]">Customer</span>
            <span class="font-medium text-[#0f1728]">{{
              customerLabel(booking)
            }}</span>
          </div>
          <div
            v-if="booking.booked_by_user?.email || booking.guest_email"
            class="flex items-center justify-between"
          >
            <span class="text-[#64748b]">Email</span>
            <a
              :href="`mailto:${booking.booked_by_user?.email ?? booking.guest_email}`"
              class="font-medium text-[#004e89] hover:underline"
            >
              {{ booking.booked_by_user?.email ?? booking.guest_email }}
            </a>
          </div>
          <div
            v-if="booking.booked_by_user?.phone || booking.guest_phone"
            class="flex items-center justify-between"
          >
            <span class="text-[#64748b]">Phone</span>
            <a
              :href="`tel:${booking.booked_by_user?.phone ?? booking.guest_phone}`"
              class="font-medium text-[#004e89] hover:underline"
            >
              {{ booking.booked_by_user?.phone ?? booking.guest_phone }}
            </a>
          </div>
          <div
            v-if="booking.payment_method"
            class="flex items-center justify-between"
          >
            <span class="text-[#64748b]">Payment</span>
            <span class="font-medium text-[#0f1728]">{{ paymentMethodLabel(booking.payment_method) }}</span>
          </div>
        </div>

        <!-- Read-only fields matching the edit form layout -->
        <div class="space-y-4">
          <UFormField label="Court">
            <p class="text-sm">
              {{ booking.court?.name ?? '—' }}
            </p>
          </UFormField>

          <UFormField label="Date">
            <p class="text-sm">
              {{ formatDate(booking.start_time) }}
            </p>
          </UFormField>

          <div class="grid grid-cols-2 gap-4">
            <UFormField label="Start Time">
              <p class="text-sm">
                {{ formatTime(booking.start_time) }}
              </p>
            </UFormField>
            <UFormField label="End Time">
              <p class="text-sm">
                {{ formatTime(booking.end_time) }}
              </p>
            </UFormField>
          </div>
        </div>

        <!-- Receipt Image -->
        <div
          v-if="booking.receipt_image_url"
          class="rounded-xl border border-[#dbe4ef] p-4"
        >
          <h4 class="mb-2 text-sm font-medium text-[#0f1728]">Receipt Image</h4>
          <AppImageViewer
            :src="booking.receipt_image_url"
            alt="Receipt"
            image-class="max-h-64 w-full rounded-lg object-contain bg-gray-50 border border-[#dbe4ef]"
          />
        </div>
      </div>
      <div v-else class="py-8 text-center text-sm text-[#64748b]">
        No booking selected.
      </div>
    </template>
  </AppModal>
</template>
