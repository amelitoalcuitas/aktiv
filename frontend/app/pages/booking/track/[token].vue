<script setup lang="ts">
import type { GuestTrackingBooking } from '~/composables/useGuestTracking';

definePageMeta({ layout: false });

const route = useRoute();
const token = route.params.token as string;
const toast = useToast();
const { fetchGuestBooking, uploadGuestTrackingReceipt, cancelGuestBooking } =
  useGuestTracking();

// ── Data ──────────────────────────────────────────────────────
const booking = ref<GuestTrackingBooking | null>(null);
const loading = ref(true);
const notFound = ref(false);

async function loadBooking() {
  try {
    booking.value = await fetchGuestBooking(token);
  } catch {
    notFound.value = true;
  } finally {
    loading.value = false;
  }
}

onMounted(loadBooking);

// ── Computed helpers ──────────────────────────────────────────
const isExpired = computed(() => {
  if (!booking.value?.expires_at) return false;
  return Date.now() > new Date(booking.value.expires_at).getTime();
});

const isEnded = computed(() => {
  if (!booking.value?.end_time) return false;
  return Date.now() > new Date(booking.value.end_time).getTime();
});

const canUpload = computed(
  () =>
    booking.value?.status === 'pending_payment' &&
    booking.value?.payment_method !== 'pay_on_site' &&
    !isExpired.value &&
    !isEnded.value
);

const canCancel = computed(
  () =>
    booking.value !== null &&
    !['cancelled', 'completed', 'confirmed'].includes(booking.value.status) &&
    !isEnded.value
);

// ── Expiry countdown ──────────────────────────────────────────
const secondsLeft = ref<number | null>(null);
let countdownTimer: ReturnType<typeof setInterval>;

function startCountdown() {
  if (!booking.value?.expires_at) return;
  const update = () => {
    const diff = Math.floor(
      (new Date(booking.value!.expires_at!).getTime() - Date.now()) / 1000
    );
    secondsLeft.value = diff > 0 ? diff : 0;
  };
  update();
  countdownTimer = setInterval(update, 1000);
}

watch(booking, (b) => {
  clearInterval(countdownTimer);
  if (b?.expires_at && b.status === 'pending_payment' && !isEnded.value) startCountdown();
});

onUnmounted(() => clearInterval(countdownTimer));

// ── Real-time slot updates (public hub channel, no auth needed) ──
const { $echo } = useNuxtApp();
// eslint-disable-next-line @typescript-eslint/no-explicit-any
let hubChannel: any = null;
let subscribedHubId: string | null = null;

watch(
  booking,
  (b) => {
    if (!b || hubChannel) return;
    subscribedHubId = b.hub.id;
    hubChannel = ($echo as any).channel(`hub.${subscribedHubId}`);
    hubChannel.listen('.booking.slot.updated', async (payload: { court_id: string }) => {
      if (payload.court_id !== b.court.id) return;
      try {
        booking.value = await fetchGuestBooking(token);
      } catch {
        // ignore
      }
    });
  },
  { immediate: true }
);

onUnmounted(() => {
  if (subscribedHubId) {
    ($echo as any).leaveChannel(`hub.${subscribedHubId}`);
    hubChannel = null;
    subscribedHubId = null;
  }
});

function formatSeconds(secs: number): string {
  const h = Math.floor(secs / 3600);
  const m = Math.floor((secs % 3600) / 60);
  const s = secs % 60;
  const parts: string[] = [];
  if (h > 0) parts.push(`${h}h`);
  if (m > 0 || h > 0) parts.push(`${m}m`);
  parts.push(`${s}s`);
  return parts.join(' ');
}

const countdownLabel = computed(() => {
  if (secondsLeft.value === null || !canUpload.value) return null;
  if (secondsLeft.value <= 0) return 'Expired';
  return `${formatSeconds(secondsLeft.value)} remaining`;
});

const payOnSiteCountdownClass = computed(() => {
  const s = secondsLeft.value;
  if (s === null) return 'bg-green-50 text-green-700';
  if (s < 20 * 60) return 'bg-red-50 text-red-700';
  if (s < 45 * 60) return 'bg-amber-50 text-amber-700';
  return 'bg-green-50 text-green-700';
});

const payOnSiteCountdownLabel = computed(() => {
  if (
    booking.value?.payment_method !== 'pay_on_site' ||
    booking.value?.status !== 'pending_payment' ||
    secondsLeft.value === null
  )
    return null;
  if (secondsLeft.value <= 0) return 'Booking time has passed';
  return `${formatSeconds(secondsLeft.value)} until your booking starts`;
});

// ── Receipt upload ────────────────────────────────────────────
const selectedFile = ref<File | null>(null);
const uploading = ref(false);
const uploadError = ref('');

function clearFile() {
  selectedFile.value = null;
}

async function submitReceipt() {
  if (!selectedFile.value) return;
  uploadError.value = '';
  uploading.value = true;
  try {
    booking.value = await uploadGuestTrackingReceipt(token, selectedFile.value);
    clearFile();
    toast.add({
      title: 'Receipt uploaded!',
      description: 'The hub owner will review your payment shortly.',
      color: 'success'
    });
  } catch (err: unknown) {
    uploadError.value =
      (err as { data?: { message?: string } })?.data?.message ??
      'Upload failed. Please try again.';
  } finally {
    uploading.value = false;
  }
}

// ── Cancel ────────────────────────────────────────────────────
const cancelling = ref(false);
const showCancelConfirm = ref(false);

async function confirmCancel() {
  cancelling.value = true;
  try {
    booking.value = await cancelGuestBooking(token);
    showCancelConfirm.value = false;
    toast.add({ title: 'Booking cancelled.', color: 'success' });
  } catch (err: unknown) {
    toast.add({
      title: 'Could not cancel',
      description:
        (err as { data?: { message?: string } })?.data?.message ??
        'Please try again.',
      color: 'error'
    });
  } finally {
    cancelling.value = false;
  }
}

// ── Formatting helpers ────────────────────────────────────────
function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('en-PH', {
    timeZone: 'Asia/Manila',
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  });
}

function formatTime(iso: string): string {
  return new Date(iso).toLocaleTimeString('en-PH', {
    timeZone: 'Asia/Manila',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  });
}

const statusConfig: Record<
  string,
  { label: string; color: 'warning' | 'info' | 'success' | 'error' | 'neutral' }
> = {
  pending_payment: { label: 'Awaiting Payment', color: 'warning' },
  payment_sent: { label: 'Receipt Submitted', color: 'info' },
  confirmed: { label: 'Confirmed', color: 'success' },
  cancelled: { label: 'Cancelled', color: 'error' },
  completed: { label: 'Completed', color: 'neutral' }
};
</script>

<template>
  <div class="min-h-screen bg-[var(--aktiv-background)] px-4 py-10">
    <div class="mx-auto max-w-md">
      <!-- Loading -->
      <div v-if="loading" class="flex items-center justify-center py-24">
        <UIcon
          name="i-heroicons-arrow-path"
          class="h-8 w-8 animate-spin text-[#004e89]"
        />
      </div>

      <!-- Not found -->
      <UCard v-else-if="notFound" :ui="{ root: 'ring-1 ring-[#dbe4ef]' }">
        <div class="py-8 text-center">
          <UIcon
            name="i-heroicons-exclamation-circle"
            class="mx-auto h-12 w-12 text-[#94a3b8]"
          />
          <p class="mt-3 font-semibold text-[#0f1728]">Booking not found</p>
          <p class="mt-1 text-sm text-[#64748b]">
            This link is invalid or has expired.
          </p>
        </div>
      </UCard>

      <!-- Booking card -->
      <template v-else-if="booking">
        <!-- Header -->
        <div class="mb-6 text-center">
          <p class="text-sm text-[#64748b]">{{ booking.hub.name }}</p>
          <h1 class="mt-0.5 text-2xl font-bold text-[#0f1728]">
            Booking Status
          </h1>
        </div>

        <UCard :ui="{ root: 'ring-1 ring-[#dbe4ef]' }">
          <template #header>
            <div class="flex items-center justify-between">
              <div>
                <p class="font-semibold text-[#0f1728]">
                  {{ booking.court.name }}
                </p>
                <p class="text-sm text-[#64748b]">
                  {{ formatDate(booking.start_time) }}
                </p>
                <p class="text-sm text-[#64748b]">
                  {{ formatTime(booking.start_time) }} –
                  {{ formatTime(booking.end_time) }}
                </p>
              </div>
              <UBadge
                :label="statusConfig[booking.status]?.label ?? booking.status"
                :color="statusConfig[booking.status]?.color ?? 'neutral'"
                variant="subtle"
              />
            </div>
          </template>

          <div class="space-y-4">
            <!-- Booking details -->
            <div
              class="rounded-xl border border-[#dbe4ef] bg-[var(--aktiv-background)] px-4 py-3 text-sm"
            >
              <table class="w-full">
                <tbody>
                  <tr v-if="booking.guest_name">
                    <td class="py-1 text-[#64748b]">Name</td>
                    <td class="py-1 text-right font-medium text-[#0f1728]">
                      {{ booking.guest_name }}
                    </td>
                  </tr>
                  <tr>
                    <td class="py-1 text-[#64748b]">Booking code</td>
                    <td
                      class="py-1 text-right font-mono font-semibold text-[#0f1728]"
                    >
                      {{ booking.booking_code }}
                    </td>
                  </tr>
                  <tr v-if="booking.total_price">
                    <td class="py-1 text-[#64748b]">Amount</td>
                    <td class="py-1 text-right font-bold text-[#004e89]">
                      ₱{{
                        Number(booking.total_price).toLocaleString('en-PH', {
                          minimumFractionDigits: 2
                        })
                      }}
                    </td>
                  </tr>
                  <tr v-if="booking.payment_method">
                    <td class="py-1 text-[#64748b]">Payment</td>
                    <td class="py-1 text-right font-medium text-[#0f1728]">
                      {{
                        booking.payment_method === 'pay_on_site'
                          ? 'Pay on site'
                          : booking.payment_method === 'digital_bank'
                            ? 'Digital bank transfer'
                            : booking.payment_method
                      }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- pay_on_site pending: show venue instruction -->
            <div
              v-if="
                booking.status === 'pending_payment' &&
                booking.payment_method === 'pay_on_site'
              "
              class="space-y-3"
            >
              <!-- Countdown -->
              <div
                v-if="payOnSiteCountdownLabel"
                :class="[
                  'flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium',
                  payOnSiteCountdownClass
                ]"
              >
                <UIcon
                  name="i-heroicons-clock"
                  class="h-4 w-4 flex-shrink-0"
                />
                <span>{{ payOnSiteCountdownLabel }}</span>
              </div>

              <!-- Pay at venue note -->
              <div
                class="rounded-lg border border-[#dbe4ef] bg-[var(--aktiv-background)] px-3 py-2 text-sm text-[#64748b]"
              >
                <p class="font-medium text-[#0f1728]">Pay at the venue</p>
                <p class="mt-0.5">
                  Show your booking code at the venue to complete payment.
                </p>
              </div>

              <!-- Running late notice -->
              <div
                class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"
              >
                <p class="font-medium">Running late?</p>
                <p class="mt-0.5">
                  Please contact the hub directly so they can hold your slot.
                </p>
                <div
                  v-if="booking.hub.phones.length || booking.hub.websites.length"
                  class="mt-2 space-y-1"
                >
                  <a
                    v-for="phone in booking.hub.phones"
                    :key="phone"
                    :href="`tel:${phone}`"
                    class="flex items-center gap-1.5 font-medium underline"
                  >
                    <UIcon name="i-heroicons-phone" class="h-3.5 w-3.5" />
                    {{ phone }}
                  </a>
                  <AppLinksList
                    :links="booking.hub.websites"
                    list-class="flex flex-wrap items-center gap-3"
                    link-class="text-[#0f1728] transition hover:text-[var(--aktiv-primary)]"
                    icon-class="h-4 w-4"
                  />
                </div>
              </div>
            </div>

            <!-- QR code -->
            <div class="flex flex-col items-center py-2">
              <p class="mb-2 text-xs text-[#64748b]">Scan at the venue</p>
              <div class="rounded-xl border border-[#dbe4ef] bg-white p-3">
                <img
                  :src="`/api/bookings/${booking.booking_code}/qr`"
                  alt="Booking QR code"
                  width="160"
                  height="160"
                />
              </div>
            </div>

            <!-- Ended banner -->
            <div
              v-if="
                isEnded && !['cancelled', 'completed'].includes(booking.status)
              "
              class="rounded-lg bg-[#f1f5f9] px-3 py-2 text-sm text-[#64748b]"
            >
              This booking has ended.
            </div>

            <!-- Status-specific messages -->

            <!-- pending_payment + rejection note -->
            <div
              v-if="
                booking.status === 'pending_payment' &&
                booking.payment_note &&
                booking.payment_method !== 'pay_on_site'
              "
              class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
            >
              <p class="font-medium">Receipt rejected</p>
              <p class="mt-0.5">{{ booking.payment_note }}</p>
              <p class="mt-1 text-xs">Please upload a new receipt below.</p>
            </div>

            <!-- payment_sent -->
            <div
              v-if="booking.status === 'payment_sent'"
              class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-700"
            >
              <p class="font-medium">Receipt submitted</p>
              <p class="mt-0.5">
                The hub owner will review your payment and confirm your booking
                shortly.
              </p>
            </div>

            <!-- confirmed -->
            <div
              v-if="booking.status === 'confirmed'"
              class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700"
            >
              <p class="font-medium">Your booking is confirmed!</p>
              <p class="mt-0.5">
                Show your booking code
                <span class="font-mono font-bold">{{
                  booking.booking_code
                }}</span>
                at the venue.
              </p>
            </div>

            <!-- cancelled -->
            <div
              v-if="booking.status === 'cancelled'"
              class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
            >
              <p class="font-medium">Booking cancelled</p>
              <p v-if="booking.payment_note" class="mt-0.5">
                {{ booking.payment_note }}
              </p>
            </div>

            <!-- Receipt upload section -->
            <div v-if="canUpload">
              <!-- Countdown -->
              <div
                v-if="countdownLabel"
                :class="[
                  'mb-3 flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium',
                  secondsLeft !== null && secondsLeft < 300
                    ? 'bg-amber-50 text-amber-700'
                    : 'bg-[#e8f0f8] text-[#004e89]'
                ]"
              >
                <UIcon name="i-heroicons-clock" class="h-4 w-4 flex-shrink-0" />
                <span>Upload before expiry — {{ countdownLabel }}</span>
              </div>

              <AppImageUploader
                v-model="selectedFile"
                hint="Upload your GCash, bank transfer, or payment screenshot. JPG, PNG or WebP, max 10 MB."
              />

              <p v-if="uploadError" class="mt-1.5 text-xs text-red-600">
                {{ uploadError }}
              </p>

              <UButton
                class="mt-3 w-full bg-[#004e89] hover:bg-[#003d6b]"
                :disabled="!selectedFile"
                :loading="uploading"
                icon="i-heroicons-arrow-up-tray"
                block
                @click="submitReceipt"
              >
                Upload Receipt
              </UButton>
            </div>

            <!-- Expired upload state -->
            <div
              v-if="
                booking.status === 'pending_payment' &&
                (isExpired || isEnded) &&
                !canUpload &&
                booking.payment_method !== 'pay_on_site'
              "
              class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
            >
              <p class="font-medium">Payment window closed</p>
              <p class="mt-0.5 text-xs">
                The time to upload a receipt has passed. Please contact the hub
                directly.
              </p>
            </div>
          </div>

          <template v-if="canCancel" #footer>
            <div class="flex justify-end">
              <UButton
                v-if="!showCancelConfirm"
                color="error"
                variant="ghost"
                size="sm"
                @click="showCancelConfirm = true"
              >
                Cancel booking
              </UButton>
              <div v-else class="flex items-center gap-2">
                <p class="text-sm text-[#64748b]">Cancel this booking?</p>
                <UButton
                  color="neutral"
                  variant="outline"
                  size="sm"
                  @click="showCancelConfirm = false"
                >
                  No
                </UButton>
                <UButton
                  color="error"
                  size="sm"
                  :loading="cancelling"
                  @click="confirmCancel"
                >
                  Yes, cancel
                </UButton>
              </div>
            </div>
          </template>
        </UCard>
      </template>
    </div>
  </div>
</template>
