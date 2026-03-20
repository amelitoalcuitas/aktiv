<script setup lang="ts">
import QRCode from 'qrcode';
import type { Booking } from '~/types/booking';

const props = defineProps<{
  open: boolean;
  booking: Booking | null;
  courtName?: string;
}>();

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
}>();

const canvasRef = ref<HTMLCanvasElement | null>(null);

// Draw QR code onto canvas whenever booking changes or modal opens
watch(
  () => [props.open, props.booking],
  async () => {
    if (!props.open || !props.booking?.booking_code || !canvasRef.value) return;
    await nextTick();
    await drawQr();
  }
);

async function drawQr() {
  const canvas = canvasRef.value;
  if (!canvas || !props.booking?.booking_code) return;

  await QRCode.toCanvas(canvas, props.booking.booking_code, {
    width: 220,
    margin: 2,
    color: { dark: '#0f1728', light: '#ffffff' },
  });
}

async function downloadQr() {
  if (!props.booking?.booking_code) return;

  // Create an off-screen canvas that includes the QR + booking code text
  const qrSize = 220;
  const padding = 20;
  const textHeight = 50;
  const totalWidth = qrSize + padding * 2;
  const totalHeight = qrSize + textHeight + padding * 2;

  const offCanvas = document.createElement('canvas');
  offCanvas.width = totalWidth;
  offCanvas.height = totalHeight;
  const ctx = offCanvas.getContext('2d')!;

  // White background
  ctx.fillStyle = '#ffffff';
  ctx.fillRect(0, 0, totalWidth, totalHeight);

  // Draw QR onto a temp canvas then copy
  const tempCanvas = document.createElement('canvas');
  await QRCode.toCanvas(tempCanvas, props.booking.booking_code, {
    width: qrSize,
    margin: 2,
    color: { dark: '#0f1728', light: '#ffffff' },
  });
  ctx.drawImage(tempCanvas, padding, padding);

  // Draw booking code text below QR
  ctx.fillStyle = '#0f1728';
  ctx.font = 'bold 18px monospace';
  ctx.textAlign = 'center';
  ctx.fillText(props.booking.booking_code, totalWidth / 2, qrSize + padding + 30);

  ctx.font = '11px sans-serif';
  ctx.fillStyle = '#64748b';
  ctx.fillText('Show this at the venue', totalWidth / 2, qrSize + padding + 48);

  // Trigger download
  const link = document.createElement('a');
  link.download = `booking-${props.booking.booking_code}.png`;
  link.href = offCanvas.toDataURL('image/png');
  link.click();
}

function formatTime(iso: string) {
  return new Date(iso).toLocaleTimeString('en-PH', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
    timeZone: 'Asia/Manila',
  });
}

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString('en-PH', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    timeZone: 'Asia/Manila',
  });
}
</script>

<template>
  <UModal
    :open="open"
    @update:open="emit('update:open', $event)"
    :ui="{ width: 'sm:max-w-sm' }"
  >
    <template #content>
      <div class="p-6">
        <!-- Header -->
        <div class="mb-5 text-center">
          <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-green-100">
            <UIcon name="i-heroicons-check-circle" class="h-6 w-6 text-green-600" />
          </div>
          <h2 class="text-lg font-semibold text-[#0f1728]">Booking Confirmed!</h2>
          <p class="mt-1 text-sm text-[#64748b]">Show this code at the venue to confirm your spot.</p>
        </div>

        <!-- Booking summary -->
        <div v-if="booking" class="mb-4 rounded-lg border border-[#dbe4ef] bg-[#f9fdf2] px-4 py-3 text-sm">
          <div v-if="courtName" class="font-medium text-[#0f1728]">{{ courtName }}</div>
          <div class="text-[#64748b]">{{ formatDate(booking.start_time) }}</div>
          <div class="text-[#64748b]">{{ formatTime(booking.start_time) }} – {{ formatTime(booking.end_time) }}</div>
          <div v-if="booking.total_price" class="mt-1 font-semibold text-[#004e89]">₱{{ booking.total_price }}</div>
        </div>

        <!-- QR code -->
        <div class="flex flex-col items-center gap-3">
          <div class="rounded-xl border border-[#dbe4ef] bg-white p-3">
            <canvas ref="canvasRef" class="block" />
          </div>

          <!-- Booking code -->
          <div v-if="booking?.booking_code" class="text-center">
            <p class="text-xs text-[#64748b]">Booking Code</p>
            <p class="mt-0.5 font-mono text-2xl font-bold tracking-widest text-[#0f1728]">
              {{ booking.booking_code }}
            </p>
          </div>

          <!-- Instructions -->
          <p class="text-center text-xs text-[#64748b]">
            Show the QR code or tell the hub owner your booking code. They'll scan it to confirm your payment on arrival.
          </p>

          <!-- Actions -->
          <div class="flex w-full gap-2">
            <UButton
              variant="outline"
              class="flex-1"
              icon="i-heroicons-arrow-down-tray"
              @click="downloadQr"
            >
              Download
            </UButton>
            <UButton
              class="flex-1 bg-[#004e89] hover:bg-[#003d6b]"
              @click="emit('update:open', false)"
            >
              Done
            </UButton>
          </div>
        </div>
      </div>
    </template>
  </UModal>
</template>
