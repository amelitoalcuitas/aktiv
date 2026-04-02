<script setup lang="ts">
import type { OpenPlayParticipant, OpenPlaySession } from '~/types/openPlay';

const props = defineProps<{
  open: boolean;
  hubId: string;
  session: OpenPlaySession | null;
  participant: OpenPlayParticipant | null;
}>();

const emit = defineEmits<{
  'update:open': [boolean];
  uploaded: [];
}>();

const toast = useToast();
const { uploadParticipantReceipt } = useOpenPlay();

const isOpen = computed({
  get: () => props.open,
  set: (value) => emit('update:open', value)
});

const selectedFile = ref<File | null>(null);
const uploadError = ref('');
const uploading = ref(false);
const secondsLeft = ref<number | null>(null);
let countdownTimer: ReturnType<typeof setInterval> | null = null;

function clearFile() {
  selectedFile.value = null;
}

function startCountdown() {
  if (!props.participant?.expires_at) return;

  const update = () => {
    const diff = Math.floor(
      (new Date(props.participant!.expires_at!).getTime() - Date.now()) / 1000
    );
    secondsLeft.value = diff > 0 ? diff : 0;
  };

  update();
  countdownTimer = setInterval(update, 1000);
}

watch(
  () => props.open,
  (open) => {
    if (countdownTimer) clearInterval(countdownTimer);

    if (open) {
      clearFile();
      uploadError.value = '';
      secondsLeft.value = null;
      startCountdown();
    }
  }
);

onUnmounted(() => {
  if (countdownTimer) clearInterval(countdownTimer);
});

const countdownLabel = computed(() => {
  if (secondsLeft.value === null) return null;
  if (secondsLeft.value <= 0) return 'Expired';

  const m = Math.floor(secondsLeft.value / 60);
  const s = secondsLeft.value % 60;
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')} remaining`;
});

const isExpired = computed(
  () => secondsLeft.value !== null && secondsLeft.value <= 0
);

function formatSchedule(session: OpenPlaySession): string {
  if (!session.booking) return '';
  const timezone = session.booking.hub_timezone ?? session.booking.court?.hub_timezone;
  const dateLabel = formatInHubTimezone(session.booking.start_time, {
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  }, 'en-PH', timezone);
  const startLabel = formatInHubTimezone(session.booking.start_time, {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  }, 'en-PH', timezone);
  const endLabel = formatInHubTimezone(session.booking.end_time, {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  }, 'en-PH', timezone);

  return `${dateLabel} · ${startLabel} – ${endLabel}`;
}

async function submit() {
  if (!props.session || !props.participant || !selectedFile.value) return;

  uploadError.value = '';
  uploading.value = true;

  try {
    await uploadParticipantReceipt(
      props.hubId,
      props.session.id,
      props.participant.id,
      selectedFile.value,
      props.participant.guest_tracking_token
    );

    toast.add({
      title: 'Receipt uploaded!',
      description: 'The hub owner will review your payment shortly.',
      color: 'success'
    });
    isOpen.value = false;
    emit('uploaded');
  } catch (err: unknown) {
    uploadError.value =
      (err as { data?: { message?: string } })?.data?.message ??
      'Upload failed. Please try again.';
  } finally {
    uploading.value = false;
  }
}
</script>

<template>
  <AppModal v-model:open="isOpen" title="Upload Open Play Receipt">
    <template #body>
      <div class="space-y-4">
        <div
          v-if="session"
          class="rounded-xl border border-[var(--aktiv-border)] bg-[var(--aktiv-background)] p-3 text-sm"
        >
          <p class="font-semibold text-[var(--aktiv-ink)]">
            Open Play · {{ session.booking?.court?.name ?? 'Court' }}
          </p>
          <p class="mt-0.5 text-[var(--aktiv-muted)]">
            {{ formatSchedule(session) }}
          </p>
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
            This participant slot has expired and can no longer receive a receipt.
          </span>
          <span v-else>
            Upload before your join expires — {{ countdownLabel }}
          </span>
        </div>

        <div v-if="!isExpired">
          <AppImageUploader
            v-model="selectedFile"
            hint="Upload your GCash, bank transfer, or payment screenshot. JPG, PNG or WebP, max 10 MB."
          />
          <p v-if="uploadError" class="mt-1.5 text-xs text-red-600">
            {{ uploadError }}
          </p>
        </div>
      </div>
    </template>

    <template #footer>
      <div class="flex justify-end gap-2">
        <UButton color="neutral" variant="outline" @click="isOpen = false">
          Close
        </UButton>
        <UButton
          v-if="!isExpired"
          color="primary"
          :disabled="!selectedFile"
          :loading="uploading"
          icon="i-heroicons-arrow-up-tray"
          @click="submit"
        >
          Upload Receipt
        </UButton>
      </div>
    </template>
  </AppModal>
</template>
