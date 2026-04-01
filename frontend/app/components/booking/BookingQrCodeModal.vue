<script setup lang="ts">
import type { Booking } from '~/types/booking';

const props = defineProps<{
  open: boolean;
  booking: Booking | null;
  courtName?: string;
}>();

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
}>();

const qrSrc = computed(() =>
  props.booking?.booking_code
    ? `/api/bookings/${props.booking.booking_code}/qr`
    : null
);

async function downloadQr() {
  if (!props.booking?.booking_code || !qrSrc.value) return;

  const qrSize = 300;
  const padding = 20;
  const textHeight = 52;
  const totalWidth = qrSize + padding * 2;
  const totalHeight = qrSize + textHeight + padding * 2;

  const canvas = document.createElement('canvas');
  canvas.width = totalWidth;
  canvas.height = totalHeight;
  const ctx = canvas.getContext('2d')!;

  ctx.fillStyle = '#ffffff';
  ctx.fillRect(0, 0, totalWidth, totalHeight);

  // Load SVG via an img element (works cross-browser for SVG→canvas)
  const img = new Image();
  img.width = qrSize;
  img.height = qrSize;
  img.src = qrSrc.value;
  await new Promise<void>((resolve, reject) => {
    img.onload = () => resolve();
    img.onerror = reject;
  });
  ctx.drawImage(img, padding, padding, qrSize, qrSize);

  ctx.fillStyle = '#0f1728';
  ctx.font = 'bold 20px monospace';
  ctx.textAlign = 'center';
  ctx.fillText(
    props.booking.booking_code,
    totalWidth / 2,
    qrSize + padding + 30
  );

  ctx.font = '12px sans-serif';
  ctx.fillStyle = '#64748b';
  ctx.fillText('Show this at the venue', totalWidth / 2, qrSize + padding + 50);

  const link = document.createElement('a');
  link.download = `booking-${props.booking.booking_code}.png`;
  link.href = canvas.toDataURL('image/png');
  link.click();
}

function formatTime(iso: string) {
  return new Date(iso).toLocaleTimeString('en-PH', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
    timeZone: 'Asia/Manila'
  });
}

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString('en-PH', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    timeZone: 'Asia/Manila'
  });
}
</script>

<template>
  <AppModal
    :open="open"
    @update:open="emit('update:open', $event)"
    :ui="{ content: 'sm:max-w-sm' }"
  >
    <template #content>
      <div class="flex max-h-[90vh] flex-col">
        <div class="flex-1 overflow-y-auto p-6">
          <!-- Header -->
          <div class="mb-5 text-center">
            <div
              class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-green-100"
            >
              <UIcon
                name="i-heroicons-check-circle"
                class="h-6 w-6 text-green-600"
              />
            </div>
            <h2 class="text-lg font-semibold text-[#0f1728]">
              Booking Confirmed!
            </h2>
            <p class="mt-1 text-sm text-[#64748b]">
              Show this code at the venue to confirm your spot.
            </p>
          </div>

          <!-- Booking summary -->
          <div
            v-if="booking"
            class="mb-4 rounded-lg border border-[#dbe4ef] bg-[var(--aktiv-background)] px-4 py-3 text-sm"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <div v-if="courtName" class="font-medium text-[#0f1728]">
                  {{ courtName }}
                </div>
                <div class="text-[#64748b]">
                  {{ formatDate(booking.start_time) }}
                </div>
                <div class="text-[#64748b]">
                  {{ formatTime(booking.start_time) }} –
                  {{ formatTime(booking.end_time) }}
                </div>
              </div>
              <template v-if="booking.total_price">
                <div class="flex flex-col items-end shrink-0">
                  <div v-if="booking.original_price" class="text-xs line-through text-[#64748b]">
                    ₱{{ Number(booking.original_price).toLocaleString('en-PH') }}
                  </div>
                  <div class="text-lg font-bold text-[#004e89]">
                    ₱{{ Number(booking.total_price).toLocaleString('en-PH') }}
                  </div>
                  <div
                    v-if="booking.discount_amount"
                    class="rounded-full bg-[#fde68a] px-2 py-0.5 text-xs font-semibold text-[#854d0e]"
                  >
                    Saved ₱{{ Number(booking.discount_amount).toLocaleString('en-PH') }}
                  </div>
                </div>
              </template>
            </div>
          </div>

          <!-- QR code -->
          <div class="flex flex-col items-center gap-3">
            <div class="rounded-xl border border-[#dbe4ef] bg-white p-3">
              <img
                v-if="qrSrc"
                :src="qrSrc"
                alt="Booking QR code"
                class="block h-[220px] w-[220px]"
              />
            </div>

            <!-- Booking code -->
            <div v-if="booking?.booking_code" class="text-center">
              <p class="text-xs text-[#64748b]">Booking Code</p>
              <p
                class="mt-0.5 font-mono text-2xl font-bold tracking-widest text-[#0f1728]"
              >
                {{ booking.booking_code }}
              </p>
            </div>

            <!-- Instructions -->
            <p class="text-center text-xs text-[#64748b]">
              Show the QR code or tell the hub owner your booking code. They'll
              scan it to confirm your payment on arrival.
            </p>
          </div>
        </div>

        <!-- Sticky footer -->
        <div class="border-t border-[#dbe4ef] bg-white p-4">
          <!-- Track link for guest bookings -->
          <NuxtLink
            v-if="booking?.guest_tracking_token"
            :to="`/booking/track/${booking.guest_tracking_token}`"
            class="mb-2 block w-full rounded-md bg-[#004e89] px-4 py-2.5 text-center text-sm font-semibold text-white"
            @click="emit('update:open', false)"
          >
            Track Your Booking
          </NuxtLink>

          <!-- Actions -->
          <div class="flex w-full gap-2">
            <UButton
              variant="outline"
              class="flex-1"
              icon="i-heroicons-arrow-down-tray"
              block
              @click="downloadQr"
            >
              Download
            </UButton>
            <UButton
              color="primary"
              class="flex-1"
              block
              @click="emit('update:open', false)"
            >
              Done
            </UButton>
          </div>
        </div>
      </div>
    </template>
  </AppModal>
</template>
