<script setup lang="ts">
import type { CalendarBooking } from '~/types/booking';
import { useBooking } from '~/composables/useBooking';

const props = defineProps<{
  open: boolean;
  booking: CalendarBooking | null;
  hubId: string | number;
  courtId: string | number;
  courtName?: string;
}>();

const emit = defineEmits<{
  'update:open': [boolean];
  'receipt-uploaded': [];
}>();

const toast = useToast();
const { uploadReceipt } = useBooking();

const isOpen = computed({
  get: () => props.open,
  set: (v) => emit('update:open', v)
});

// ── File selection ─────────────────────────────────────────────
const selectedFile = ref<File | null>(null);

function clearFile() {
  selectedFile.value = null;
}

// ── Expiry countdown ─────────────────────────────────────────
const secondsLeft = ref<number | null>(null);
let countdownTimer: ReturnType<typeof setInterval>;

function startCountdown() {
  if (!props.booking?.expires_at) return;
  const update = () => {
    const diff = Math.floor(
      (new Date(props.booking!.expires_at!).getTime() - Date.now()) / 1000
    );
    secondsLeft.value = diff > 0 ? diff : 0;
  };
  update();
  countdownTimer = setInterval(update, 1000);
}

watch(
  () => props.open,
  (open) => {
    if (open) {
      clearFile();
      startCountdown();
    } else {
      clearInterval(countdownTimer);
      secondsLeft.value = null;
    }
  }
);

onUnmounted(() => clearInterval(countdownTimer));

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

// ── Upload ────────────────────────────────────────────────────
const uploading = ref(false);
const uploadError = ref('');

async function submit() {
  if (!props.booking || !selectedFile.value) return;

  uploadError.value = '';
  uploading.value = true;
  try {
    await uploadReceipt(
      props.hubId,
      props.courtId,
      props.booking.id,
      selectedFile.value
    );
    toast.add({
      title: 'Receipt uploaded!',
      description: 'The hub owner will review your payment shortly.',
      color: 'success'
    });
    isOpen.value = false;
    emit('receipt-uploaded');
  } catch (err: unknown) {
    uploadError.value =
      (err as { data?: { message?: string } })?.data?.message ??
      'Upload failed. Please try again.';
  } finally {
    uploading.value = false;
  }
}

// ── Helpers ───────────────────────────────────────────────────
function formatRange(start: string, end: string): string {
  const s = new Date(start);
  const e = new Date(end);
  const dateStr = s.toLocaleDateString('en-PH', {
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  });
  const timeStart = s.toLocaleTimeString('en-PH', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  });
  const timeEnd = e.toLocaleTimeString('en-PH', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  });
  return `${dateStr} · ${timeStart} – ${timeEnd}`;
}
</script>

<template>
  <AppModal v-model:open="isOpen" title="Upload Payment Receipt">
    <template #body>
      <div class="space-y-4">
        <!-- Booking summary -->
        <div
          v-if="booking"
          class="rounded-xl border border-[var(--aktiv-border)] bg-[var(--aktiv-bg)] p-3 text-sm"
        >
          <p class="font-semibold text-[var(--aktiv-ink)]">
            {{ courtName ?? `Court #${booking.id}` }}
          </p>
          <p class="mt-0.5 text-[var(--aktiv-muted)]">
            {{ formatRange(booking.start_time, booking.end_time) }}
          </p>
        </div>

        <!-- Expiry notice -->
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
          <UIcon
            :name="isExpired ? 'i-heroicons-clock' : 'i-heroicons-clock'"
            class="h-4 w-4 flex-shrink-0"
          />
          <span v-if="isExpired">
            This booking has expired and can no longer receive a receipt.
          </span>
          <span v-else>
            Upload before the booking expires — {{ countdownLabel }}
          </span>
        </div>

        <!-- File upload area -->
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
          :disabled="!selectedFile || !!fileError"
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
