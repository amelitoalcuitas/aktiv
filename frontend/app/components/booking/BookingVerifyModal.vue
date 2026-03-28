<script setup lang="ts">
import type { Hub } from '~/types/hub';
import type { BookingDetail } from '~/types/booking';
import { useBooking } from '~/composables/useBooking';

const props = defineProps<{
  open: boolean;
  hub: Hub | null;
}>();

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
}>();

const { verifyBookingByCode, confirmBooking, rejectBooking } = useBooking();
const toast = useToast();

// ── Tabs ──────────────────────────────────────────────────────
type Tab = 'scan' | 'manual';
const activeTab = ref<Tab>('scan');

// ── Manual entry ──────────────────────────────────────────────
const manualCode = ref('');

// ── Camera / QR scanner ───────────────────────────────────────
const videoRef = ref<HTMLVideoElement | null>(null);
const cameraError = ref<string | null>(null);
const isScanning = ref(false);
let stopScanning: (() => void) | null = null;

async function startCamera() {
  cameraError.value = null;
  isScanning.value = true;
  stopScanning?.();

  try {
    const { BrowserQRCodeReader } = await import('@zxing/browser');
    const reader = new BrowserQRCodeReader();

    const stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: { ideal: 'environment' } }
    });

    if (videoRef.value) {
      videoRef.value.srcObject = stream;
      videoRef.value.play();
    }

    const controls = await reader.decodeFromVideoElement(
      videoRef.value!,
      (result, _err, controls) => {
        if (result) {
          const code = result.getText();
          controls.stop();
          stopVideoStream();
          handleCode(code);
        }
      }
    );

    stopScanning = () => {
      controls.stop();
      stopVideoStream();
    };
  } catch (err: unknown) {
    isScanning.value = false;
    const msg = err instanceof Error ? err.message : 'Camera unavailable';
    if (
      msg.toLowerCase().includes('permission') ||
      msg.toLowerCase().includes('denied')
    ) {
      cameraError.value =
        'Camera permission denied. Please allow camera access and try again.';
    } else {
      cameraError.value =
        'Could not start camera. Try entering the code manually.';
    }
  }
}

function stopVideoStream() {
  isScanning.value = false;
  if (videoRef.value?.srcObject) {
    const tracks = (videoRef.value.srcObject as MediaStream).getTracks();
    tracks.forEach((t) => t.stop());
    videoRef.value.srcObject = null;
  }
}

watch(
  () => props.open,
  (val) => {
    if (!val) {
      stopScanning?.();
      stopVideoStream();
      reset();
    } else if (activeTab.value === 'scan') {
      nextTick(startCamera);
    }
  }
);

watch(activeTab, (tab) => {
  if (tab === 'scan') {
    nextTick(startCamera);
  } else {
    stopScanning?.();
    stopVideoStream();
  }
});

// ── Verify ────────────────────────────────────────────────────
const foundBooking = ref<BookingDetail | null>(null);
const isVerifying = ref(false);
const verifyError = ref<string | null>(null);

async function handleCode(code: string) {
  if (!props.hub || !code.trim()) return;
  isVerifying.value = true;
  verifyError.value = null;
  foundBooking.value = null;

  try {
    foundBooking.value = await verifyBookingByCode(props.hub.id, code.trim());
  } catch (err: unknown) {
    const status = (err as { statusCode?: number })?.statusCode;
    verifyError.value =
      status === 404
        ? 'No booking found with that code.'
        : 'Something went wrong. Please try again.';
  } finally {
    isVerifying.value = false;
  }
}

function submitManual() {
  handleCode(manualCode.value);
}

// ── Confirm / Reject ──────────────────────────────────────────
const isActing = ref(false);
const showRejectPrompt = ref(false);
const rejectNote = ref('');

async function onConfirm() {
  if (!props.hub || !foundBooking.value) return;
  isActing.value = true;
  try {
    await confirmBooking(props.hub.id, foundBooking.value.id);
    toast.add({ title: 'Booking confirmed!', color: 'success' });
    emit('update:open', false);
  } catch {
    toast.add({ title: 'Failed to confirm booking', color: 'error' });
  } finally {
    isActing.value = false;
  }
}

async function onReject() {
  if (!props.hub || !foundBooking.value) return;
  isActing.value = true;
  try {
    await rejectBooking(props.hub.id, foundBooking.value.id, rejectNote.value);
    toast.add({ title: 'Booking rejected', color: 'info' });
    emit('update:open', false);
  } catch {
    toast.add({ title: 'Failed to reject booking', color: 'error' });
  } finally {
    isActing.value = false;
    showRejectPrompt.value = false;
  }
}

function reset() {
  foundBooking.value = null;
  verifyError.value = null;
  manualCode.value = '';
  rejectNote.value = '';
  showRejectPrompt.value = false;
  isVerifying.value = false;
}

// ── Helpers ───────────────────────────────────────────────────
function formatDateTime(iso: string) {
  return new Date(iso).toLocaleString('en-PH', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
    timeZone: 'Asia/Manila'
  });
}

const statusColors: Record<string, string> = {
  pending_payment: 'bg-amber-100 text-amber-700',
  payment_sent: 'bg-blue-100 text-blue-700',
  confirmed: 'bg-green-100 text-green-700',
  cancelled: 'bg-red-100 text-red-700',
  completed: 'bg-gray-100 text-gray-600'
};

const statusLabels: Record<string, string> = {
  pending_payment: 'Pending Payment',
  payment_sent: 'Payment Sent',
  confirmed: 'Confirmed',
  cancelled: 'Cancelled',
  completed: 'Completed'
};
</script>

<template>
  <AppModal
    :open="open"
    @update:open="emit('update:open', $event)"
    :ui="{ width: 'sm:max-w-md' }"
  >
    <template #content>
      <div class="p-6">
        <!-- Header -->
        <div class="mb-5">
          <h2 class="text-lg font-semibold text-[#0f1728]">Verify Booking</h2>
          <p class="mt-0.5 text-sm text-[#64748b]">
            Scan a customer's QR code or enter their booking code to confirm
            payment.
          </p>
        </div>

        <!-- Found booking result -->
        <div v-if="foundBooking" class="space-y-4">
          <!-- Customer info -->
          <div
            class="rounded-xl border border-[#dbe4ef] bg-[var(--aktiv-background)] p-4 space-y-2"
          >
            <div class="flex items-center justify-between">
              <span class="text-xs text-[#64748b]">Status</span>
              <span
                class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="
                  statusColors[foundBooking.status] ??
                  'bg-gray-100 text-gray-600'
                "
              >
                {{ statusLabels[foundBooking.status] ?? foundBooking.status }}
              </span>
            </div>

            <div class="text-sm space-y-1">
              <div class="flex justify-between">
                <span class="text-[#64748b]">Customer</span>
                <span class="font-medium text-[#0f1728]">
                  {{
                    foundBooking.booked_by_user?.name ??
                    foundBooking.guest_name ??
                    'Guest'
                  }}
                </span>
              </div>
              <div
                v-if="
                  foundBooking.booked_by_user?.email || foundBooking.guest_email
                "
                class="flex justify-between"
              >
                <span class="text-[#64748b]">Email</span>
                <span class="text-[#0f1728]">{{
                  foundBooking.booked_by_user?.email ?? foundBooking.guest_email
                }}</span>
              </div>
              <div v-if="foundBooking.court" class="flex justify-between">
                <span class="text-[#64748b]">Court</span>
                <span class="font-medium text-[#0f1728]">{{
                  foundBooking.court.name
                }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-[#64748b]">Time</span>
                <span class="text-[#0f1728]">{{
                  formatDateTime(foundBooking.start_time)
                }}</span>
              </div>
              <div v-if="foundBooking.sport" class="flex justify-between">
                <span class="text-[#64748b]">Sport</span>
                <span class="capitalize text-[#0f1728]">{{
                  foundBooking.sport
                }}</span>
              </div>
              <div v-if="foundBooking.total_price" class="flex justify-between">
                <span class="text-[#64748b]">Amount</span>
                <div class="flex flex-col items-end gap-0.5">
                  <span v-if="foundBooking.original_price" class="text-sm line-through text-[#64748b]">
                    ₱{{ Number(foundBooking.original_price).toLocaleString('en-PH') }}
                  </span>
                  <span class="font-semibold text-[#004e89]">
                    ₱{{ Number(foundBooking.total_price).toLocaleString('en-PH') }}
                  </span>
                  <span
                    v-if="foundBooking.discount_amount"
                    class="rounded-full bg-[#fde68a] px-2 py-0.5 text-xs font-semibold text-[#854d0e]"
                  >
                    Saved ₱{{ Number(foundBooking.discount_amount).toLocaleString('en-PH') }}
                    <template v-if="foundBooking.applied_promo_title"> · {{ foundBooking.applied_promo_title }}</template>
                  </span>
                </div>
              </div>
              <div class="flex justify-between">
                <span class="text-[#64748b]">Code</span>
                <span
                  class="font-mono font-bold tracking-widest text-[#0f1728]"
                  >{{ foundBooking.booking_code }}</span
                >
              </div>
            </div>
          </div>

          <!-- Reject note prompt -->
          <div v-if="showRejectPrompt" class="space-y-2">
            <label class="text-sm font-medium text-[#0f1728]"
              >Reason for rejection
              <span class="text-[#64748b] font-normal">(optional)</span></label
            >
            <UTextarea
              v-model="rejectNote"
              placeholder="e.g. Payment amount does not match"
              :rows="2"
            />
            <div class="flex gap-2">
              <UButton
                variant="ghost"
                class="flex-1"
                @click="showRejectPrompt = false"
                >Cancel</UButton
              >
              <UButton
                color="error"
                class="flex-1"
                :loading="isActing"
                @click="onReject"
                >Confirm Reject</UButton
              >
            </div>
          </div>

          <!-- Actions -->
          <div v-else class="flex gap-2">
            <UButton
              variant="outline"
              color="error"
              class="flex-1"
              block
              :disabled="
                isActing ||
                ['cancelled', 'completed'].includes(foundBooking.status)
              "
              @click="showRejectPrompt = true"
            >
              Reject
            </UButton>
            <UButton
              color="primary"
              class="flex-1"
              :loading="isActing"
              block
              :disabled="
                ['confirmed', 'cancelled', 'completed'].includes(
                  foundBooking.status
                )
              "
              @click="onConfirm"
            >
              {{
                foundBooking.status === 'confirmed'
                  ? 'Already Confirmed'
                  : 'Confirm'
              }}
            </UButton>
          </div>

          <UButton
            variant="ghost"
            size="sm"
            class="w-full text-[#64748b]"
            @click="reset"
          >
            Scan another booking
          </UButton>
        </div>

        <!-- Scanner / manual input -->
        <div v-else class="space-y-4">
          <!-- Tab toggle -->
          <div
            class="flex rounded-lg border border-[#dbe4ef] bg-[var(--aktiv-background)] p-1 gap-1"
          >
            <button
              v-for="tab in [
                { key: 'scan', label: 'Scan QR', icon: 'i-heroicons-qr-code' },
                {
                  key: 'manual',
                  label: 'Enter Code',
                  icon: 'i-heroicons-hashtag'
                }
              ] as const"
              :key="tab.key"
              class="flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition-colors"
              :class="
                activeTab === tab.key
                  ? 'bg-white text-[#004e89] '
                  : 'text-[#64748b] hover:text-[#0f1728]'
              "
              @click="activeTab = tab.key"
            >
              <UIcon :name="tab.icon" class="h-4 w-4" />
              {{ tab.label }}
            </button>
          </div>

          <!-- Camera scan tab -->
          <div v-if="activeTab === 'scan'">
            <div
              v-if="cameraError"
              class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700"
            >
              {{ cameraError }}
              <button
                class="mt-2 block font-medium underline"
                @click="startCamera"
              >
                Try again
              </button>
            </div>
            <div
              v-else
              class="overflow-hidden rounded-xl border border-[#dbe4ef] bg-black"
            >
              <video ref="videoRef" class="w-full" playsinline muted />
              <p
                v-if="isScanning"
                class="py-2 text-center text-xs text-white/70"
              >
                Point camera at QR code…
              </p>
            </div>
          </div>

          <!-- Manual entry tab -->
          <div v-else class="space-y-3 flex flex-col">
            <UInput
              v-model="manualCode"
              placeholder="e.g. AKT1BX7K"
              class="font-mono text-lg tracking-wider uppercase"
              @keyup.enter="submitManual"
            />
            <UButton
              color="primary"
              :loading="isVerifying"
              :disabled="!manualCode.trim()"
              block
              @click="submitManual"
            >
              Look up Booking
            </UButton>
          </div>

          <!-- Verify error -->
          <p v-if="verifyError" class="text-sm text-red-600">
            {{ verifyError }}
          </p>

          <!-- Loading indicator while verifying (after scan) -->
          <div
            v-if="isVerifying && activeTab === 'scan'"
            class="flex items-center justify-center gap-2 text-sm text-[#64748b]"
          >
            <UIcon name="i-heroicons-arrow-path" class="h-4 w-4 animate-spin" />
            Looking up booking…
          </div>
        </div>
      </div>
    </template>
  </AppModal>
</template>
