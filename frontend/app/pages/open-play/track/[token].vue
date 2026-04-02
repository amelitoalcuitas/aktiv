<script setup lang="ts">
import type {
  GuestOpenPlayTrackingParticipant,
  GuestOpenPlayTrackingStatus
} from '~/composables/useGuestOpenPlayTracking';
import { getOpenPlayParticipantPresentation } from '~/utils/openPlayPresentation';

definePageMeta({ layout: false });

const route = useRoute();
const token = route.params.token as string;
const toast = useToast();
const {
  fetchGuestOpenPlayParticipant,
  uploadGuestOpenPlayReceipt,
  cancelGuestOpenPlayParticipant
} = useGuestOpenPlayTracking();

const participant = ref<GuestOpenPlayTrackingParticipant | null>(null);
const loading = ref(true);
const notFound = ref(false);

async function loadParticipant() {
  try {
    participant.value = await fetchGuestOpenPlayParticipant(token);
  } catch {
    notFound.value = true;
  } finally {
    loading.value = false;
  }
}

onMounted(loadParticipant);

const isExpired = computed(() => {
  if (!participant.value?.expires_at) return false;
  return Date.now() >= new Date(participant.value.expires_at).getTime();
});

const isEnded = computed(() => {
  if (!participant.value?.end_time) return false;
  return Date.now() >= new Date(participant.value.end_time).getTime();
});

const canUpload = computed(
  () =>
    participant.value?.status === 'pending_payment' &&
    participant.value?.payment_method === 'digital_bank' &&
    !isExpired.value &&
    !isEnded.value
);

const canCancel = computed(
  () =>
    participant.value !== null &&
    ['pending_payment', 'payment_sent'].includes(participant.value.status) &&
    !isEnded.value
);

const secondsLeft = ref<number | null>(null);
let countdownTimer: ReturnType<typeof setInterval>;

function startCountdown() {
  if (!participant.value?.expires_at) return;
  const update = () => {
    const diff = Math.floor(
      (new Date(participant.value!.expires_at!).getTime() - Date.now()) / 1000
    );
    secondsLeft.value = diff > 0 ? diff : 0;
  };
  update();
  countdownTimer = setInterval(update, 1000);
}

watch(participant, (value) => {
  clearInterval(countdownTimer);
  secondsLeft.value = null;
  if (
    value?.expires_at &&
    value.status === 'pending_payment' &&
    value.payment_method === 'digital_bank' &&
    !isEnded.value
  ) {
    startCountdown();
  }
});

onUnmounted(() => clearInterval(countdownTimer));

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

const participantPresentation = computed(() => {
  if (!participant.value) return null;

  return getOpenPlayParticipantPresentation({
    payment_status: participant.value.status,
    payment_method: participant.value.payment_method,
    cancelled_by: participant.value.cancelled_by,
    expires_at: participant.value.expires_at
  });
});

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
    participant.value = await uploadGuestOpenPlayReceipt(token, selectedFile.value);
    clearFile();
    toast.add({
      title: 'Receipt uploaded',
      description: 'Your status is now Under Review while the hub checks your payment.',
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

const cancelling = ref(false);
const showCancelConfirm = ref(false);

async function confirmCancel() {
  cancelling.value = true;
  try {
    participant.value = await cancelGuestOpenPlayParticipant(token);
    showCancelConfirm.value = false;
    toast.add({ title: 'Open play join cancelled.', color: 'success' });
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
  GuestOpenPlayTrackingStatus,
  { color: 'warning' | 'info' | 'success' | 'error' | 'neutral' }
> = {
  pending_payment: { color: 'warning' },
  payment_sent: { color: 'info' },
  confirmed: { color: 'success' },
  cancelled: { color: 'error' }
};

const statusLabel = computed(() => {
  return participantPresentation.value?.label ?? '';
});
</script>

<template>
  <div class="min-h-screen bg-[var(--aktiv-background)] px-4 py-10">
    <div class="mx-auto max-w-md">
      <div v-if="loading" class="flex items-center justify-center py-24">
        <UIcon
          name="i-heroicons-arrow-path"
          class="h-8 w-8 animate-spin text-[#004e89]"
        />
      </div>

      <UCard v-else-if="notFound" :ui="{ root: 'ring-1 ring-[#dbe4ef]' }">
        <div class="py-8 text-center">
          <UIcon
            name="i-heroicons-exclamation-circle"
            class="mx-auto h-12 w-12 text-[#94a3b8]"
          />
          <p class="mt-3 font-semibold text-[#0f1728]">Open play join not found</p>
          <p class="mt-1 text-sm text-[#64748b]">
            This link is invalid or has expired.
          </p>
        </div>
      </UCard>

      <template v-else-if="participant">
        <div class="mb-6 text-center">
          <p class="text-sm text-[#64748b]">{{ participant.hub.name }}</p>
          <h1 class="mt-0.5 text-2xl font-bold text-[#0f1728]">
            Open Play Status
          </h1>
        </div>

        <UCard :ui="{ root: 'ring-1 ring-[#dbe4ef]' }">
          <template #header>
            <div class="flex items-center justify-between">
              <div>
                <p class="font-semibold text-[#0f1728]">
                  {{ participant.title }}
                </p>
                <p class="text-sm text-[#64748b]">
                  {{ participant.court.name }} · {{ formatDate(participant.start_time) }}
                </p>
                <p class="text-sm text-[#64748b]">
                  {{ formatTime(participant.start_time) }} –
                  {{ formatTime(participant.end_time) }}
                </p>
              </div>
              <UBadge
                :label="statusLabel"
                :color="participantPresentation?.color ?? statusConfig[participant.status]?.color ?? 'neutral'"
                variant="subtle"
              />
            </div>
          </template>

          <div class="space-y-4">
            <div
              class="rounded-xl border border-[#dbe4ef] bg-[var(--aktiv-background)] px-4 py-3 text-sm"
            >
              <table class="w-full">
                <tbody>
                  <tr v-if="participant.guest_name">
                    <td class="py-1 text-[#64748b]">Name</td>
                    <td class="py-1 text-right font-medium text-[#0f1728]">
                      {{ participant.guest_name }}
                    </td>
                  </tr>
                  <tr>
                    <td class="py-1 text-[#64748b]">Payment</td>
                    <td class="py-1 text-right font-medium text-[#0f1728]">
                      {{
                        participant.payment_method === 'pay_on_site'
                          ? 'Pay on site'
                          : 'Digital bank transfer'
                      }}
                    </td>
                  </tr>
                  <tr>
                    <td class="py-1 text-[#64748b]">Amount</td>
                    <td class="py-1 text-right font-bold text-[#004e89]">
                      ₱{{
                        Number(participant.price_per_player).toLocaleString('en-PH', {
                          minimumFractionDigits: 2
                        })
                      }}
                    </td>
                  </tr>
                  <tr v-if="participant.description ?? participant.notes">
                    <td class="py-1 text-[#64748b]">Description</td>
                    <td class="py-1 text-right font-medium text-[#0f1728]">
                      {{ participant.description ?? participant.notes }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div
              v-if="countdownLabel"
              :class="[
                'flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium',
                isExpired
                  ? 'bg-red-50 text-red-700'
                  : secondsLeft !== null && secondsLeft < 300
                    ? 'bg-amber-50 text-amber-700'
                    : 'bg-[#e8f0f8] text-[#004e89]'
              ]"
            >
              <UIcon name="i-heroicons-clock" class="h-4 w-4 flex-shrink-0" />
              <span v-if="isExpired">
                This join expired and your reserved spot was released.
              </span>
              <span v-else>
                Upload before your join expires — {{ countdownLabel }}
              </span>
            </div>

            <div
              v-if="participantPresentation?.helperText"
              class="rounded-lg border border-[#dbe4ef] bg-[var(--aktiv-background)] px-3 py-2 text-sm text-[#64748b]"
            >
              <p class="font-medium text-[#0f1728]">{{ participantPresentation.label }}</p>
              <p class="mt-0.5">
                {{ participantPresentation.helperText }}
              </p>
            </div>

            <div
              v-if="participant.payment_note"
              class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"
            >
              <p class="font-medium">Note from the hub</p>
              <p class="mt-0.5">{{ participant.payment_note }}</p>
            </div>

            <div
              v-if="participant.receipt_image_url"
              class="rounded-lg border border-[#dbe4ef] bg-white p-3"
            >
              <p class="mb-2 text-sm font-medium text-[#0f1728]">
                Uploaded receipt
              </p>
              <img
                :src="participant.receipt_image_url"
                alt="Uploaded receipt"
                class="w-full rounded-lg object-cover"
              />
            </div>

            <div v-if="canUpload" class="space-y-3">
              <AppImageUploader
                v-model="selectedFile"
                hint="Upload your GCash, bank transfer, or payment screenshot. JPG, PNG or WebP, max 10 MB."
              />
              <p v-if="uploadError" class="text-xs text-red-600">
                {{ uploadError }}
              </p>
              <UButton
                color="primary"
                block
                :disabled="!selectedFile"
                :loading="uploading"
                icon="i-heroicons-arrow-up-tray"
                @click="submitReceipt"
              >
                Upload Receipt
              </UButton>
            </div>

            <div
              v-if="participant.hub.phones.length || participant.hub.websites.length"
              class="rounded-lg border border-[#dbe4ef] bg-[var(--aktiv-background)] px-3 py-2 text-sm text-[#64748b]"
            >
              <p class="font-medium text-[#0f1728]">Need help?</p>
              <p class="mt-0.5">Contact the hub directly.</p>
              <div class="mt-2 space-y-1">
                <a
                  v-for="phone in participant.hub.phones"
                  :key="phone"
                  :href="`tel:${phone}`"
                  class="flex items-center gap-1.5 font-medium underline"
                >
                  <UIcon name="i-heroicons-phone" class="h-3.5 w-3.5" />
                  {{ phone }}
                </a>
                <AppLinksList
                  :links="participant.hub.websites"
                  list-class="flex flex-wrap items-center gap-3"
                  link-class="text-[#0f1728] transition hover:text-[var(--aktiv-primary)]"
                  icon-class="h-4 w-4"
                />
              </div>
            </div>

            <div
              v-if="isEnded && participant.status !== 'cancelled'"
              class="rounded-lg bg-[#f1f5f9] px-3 py-2 text-sm text-[#64748b]"
            >
              This open play session has ended.
            </div>
          </div>

          <template #footer>
            <div class="flex items-center justify-between gap-2">
              <NuxtLink
                :to="hubPublicPath(participant.hub, '/open-play')"
                class="text-sm font-medium text-[#004e89] underline underline-offset-2"
              >
                View hub sessions
              </NuxtLink>
              <UButton
                v-if="canCancel"
                color="error"
                variant="outline"
                @click="showCancelConfirm = true"
              >
                Cancel Join
              </UButton>
            </div>
          </template>
        </UCard>

        <AppModal
          v-model:open="showCancelConfirm"
          title="Cancel open play join?"
          :ui="{ content: 'max-w-sm' }"
        >
          <template #body>
            <p class="text-sm text-[#64748b]">
              This will release your reserved spot for the session.
            </p>
          </template>
          <template #footer>
            <div class="flex w-full justify-end gap-2">
              <UButton
                color="neutral"
                variant="ghost"
                @click="showCancelConfirm = false"
              >
                Keep Join
              </UButton>
              <UButton
                color="error"
                :loading="cancelling"
                @click="confirmCancel"
              >
                Cancel Join
              </UButton>
            </div>
          </template>
        </AppModal>
      </template>
    </div>
  </div>
</template>
