<script setup lang="ts">
import type { Court, SportType } from '~/types/hub';
import type { Booking } from '~/types/booking';
import { useAuthStore } from '~/stores/auth';

const props = defineProps<{
  open: boolean;
  court: Court | null;
  clickedDate: Date | null;
  hubId: string;
}>();

const emit = defineEmits<{
  'update:open': [boolean];
  'booking-created': [booking: Booking];
}>();

const route = useRoute();
const toast = useToast();
const authStore = useAuthStore();
const { createBooking } = useBooking();

const isLoggedIn = computed(() => authStore.isAuthenticated);

const isOpen = computed({
  get: () => props.open,
  set: (val) => emit('update:open', val)
});

// ── Sport selection ────────────────────────────────────────────
const selectedSport = ref<SportType | undefined>(undefined);

watch(
  () => props.court,
  (court) => {
    selectedSport.value = court?.sports?.[0] ?? undefined;
  },
  { immediate: true }
);

const sportOptions = computed(() =>
  (props.court?.sports ?? []).map((s: SportType) => ({
    label: s.charAt(0).toUpperCase() + s.slice(1),
    value: s
  }))
);

// ── Session type ───────────────────────────────────────────────
const sessionType = ref<'private' | 'open_play'>('private');

// ── Start time (locked — set from clicked calendar slot) ──────
function timeStrFromDate(date: Date): string {
  const h = date.getHours();
  return `${String(h).padStart(2, '0')}:00`;
}

function formatTimeStr(value: string): string {
  const parts = value.split(':');
  const h = parseInt(parts[0] || '0', 10);
  const m = parseInt(parts[1] || '0', 10);
  const ampm = h < 12 ? 'AM' : 'PM';
  const h12 = h % 12 || 12;
  return `${h12}:${String(m).padStart(2, '0')} ${ampm}`;
}

const startTimeStr = computed(() =>
  props.clickedDate ? timeStrFromDate(props.clickedDate) : '09:00'
);

const startTimeLabel = computed(() => formatTimeStr(startTimeStr.value));

// ── End time (user-selectable, 1-hour increments from start) ──
// Options: start+1h, start+2h, … up to 23:00
const endTimeOptions = computed(() => {
  const parts = startTimeStr.value.split(':');
  const h = parseInt(parts[0] || '0', 10);
  const m = parseInt(parts[1] || '0', 10);
  const startMin = h * 60 + m;
  const options = [];
  for (let i = 1; startMin + i * 60 <= 23 * 60; i++) {
    const endMin = startMin + i * 60;
    const eh = Math.floor(endMin / 60);
    const em = endMin % 60;
    const value = `${String(eh).padStart(2, '0')}:${String(em).padStart(2, '0')}`;
    options.push({ label: formatTimeStr(value), value });
  }
  return options;
});

// Default end time = start + 1 hour; re-derive when start changes
const endTimeStr = ref('10:00');

watch(
  startTimeStr,
  (val: string) => {
    const parts = val.split(':');
    const h = parseInt(parts[0] || '0', 10);
    const m = parseInt(parts[1] || '0', 10);
    const defaultEnd = h * 60 + m + 60;
    const eh = Math.floor(defaultEnd / 60);
    const em = defaultEnd % 60;
    endTimeStr.value = `${String(eh).padStart(2, '0')}:${String(em).padStart(2, '0')}`;
  },
  { immediate: true }
);

// ── Reset on open / close ──────────────────────────────────────
watch(isOpen, (val) => {
  submitError.value = null;
  if (!val) return;
  sessionType.value = 'private';
  isSubmitting.value = false;
  // Re-apply default end time from (possibly updated) start
  const parts = startTimeStr.value.split(':');
  const h = parseInt(parts[0] || '0', 10);
  const m = parseInt(parts[1] || '0', 10);
  const defaultEnd = h * 60 + m + 60;
  const eh = Math.floor(defaultEnd / 60);
  const em = defaultEnd % 60;
  endTimeStr.value = `${String(eh).padStart(2, '0')}:${String(em).padStart(2, '0')}`;
});

// ── Computed labels ────────────────────────────────────────────
const formattedDate = computed(() => {
  if (!props.clickedDate) return '—';
  return props.clickedDate.toLocaleDateString('en-PH', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  });
});

const durationHours = computed(() => {
  const startParts = startTimeStr.value.split(':');
  const endParts = endTimeStr.value.split(':');
  const sh = parseInt(startParts[0] || '0', 10);
  const sm = parseInt(startParts[1] || '0', 10);
  const eh = parseInt(endParts[0] || '0', 10);
  const em = parseInt(endParts[1] || '0', 10);
  return (eh * 60 + em - (sh * 60 + sm)) / 60;
});

const durationLabel = computed(() => {
  const h = durationHours.value;
  return `${h} hr${h > 1 ? 's' : ''}`;
});

// ── Price ──────────────────────────────────────────────────────
const estimatedTotal = computed(() => {
  if (!props.court) return null;
  const p = parseFloat(props.court.price_per_hour);
  return isNaN(p) ? null : p * durationHours.value;
});

function formatPrice(p: number) {
  return p.toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}

// ── Submission ─────────────────────────────────────────────────
const isSubmitting = ref(false);
const submitError = ref<string | null>(null);

function buildISODateTime(date: Date, timeStr: string): string {
  const parts = timeStr.split(':');
  const h = parseInt(parts[0] || '0', 10);
  const m = parseInt(parts[1] || '0', 10);
  const dt = new Date(date);
  dt.setHours(h, m, 0, 0);
  return dt.toISOString();
}

function goToLogin() {
  isOpen.value = false;
  navigateTo(`/auth/login?redirect=${encodeURIComponent(route.fullPath)}`);
}

async function handleConfirm() {
  if (!props.court || !props.clickedDate || !selectedSport.value) return;
  isSubmitting.value = true;
  submitError.value = null;
  try {
    const booking = await createBooking(props.hubId, props.court.id, {
      sport: selectedSport.value,
      start_time: buildISODateTime(props.clickedDate, startTimeStr.value),
      end_time: buildISODateTime(props.clickedDate, endTimeStr.value),
      session_type: sessionType.value
    });
    toast.add({
      title: 'Booking created!',
      description:
        'Your slot is held. Please upload your payment receipt within 1 hour.',
      color: 'success'
    });
    emit('booking-created', booking);
    isOpen.value = false;
  } catch (e: unknown) {
    const err = e as { data?: { message?: string }; status?: number };
    submitError.value =
      err?.status === 409
        ? 'This time slot was just taken. Please choose a different time.'
        : (err?.data?.message ?? 'Booking failed. Please try again.');
  } finally {
    isSubmitting.value = false;
  }
}
</script>

<template>
  <UModal
    v-model:open="isOpen"
    :title="court?.name ?? 'Book a Court'"
    :ui="{ content: 'max-w-lg' }"
  >
    <template #body>
      <div class="space-y-5">
        <!-- Date strip (read-only) -->
        <div
          class="flex items-center gap-2 rounded-xl border border-[var(--aktiv-border)] bg-[var(--aktiv-background)] px-4 py-2.5 text-sm text-[var(--aktiv-muted)]"
        >
          <UIcon name="i-heroicons-calendar-days" class="h-4 w-4 shrink-0" />
          <span>{{ formattedDate }}</span>
        </div>

        <!-- Start & end time -->
        <div class="grid grid-cols-2 gap-3">
          <!-- Start time: locked to the clicked slot -->
          <UFormField label="Start Time">
            <div
              class="flex h-8 items-center rounded-md border border-[var(--aktiv-border)] bg-[var(--aktiv-background)] px-3 text-sm font-semibold text-[var(--aktiv-ink)]"
            >
              {{ startTimeLabel }}
            </div>
          </UFormField>

          <!-- End time: selectable, 1-hour steps -->
          <UFormField label="End Time">
            <USelect
              v-model="endTimeStr"
              :items="endTimeOptions"
              value-key="value"
              label-key="label"
              class="w-full"
            />
          </UFormField>
        </div>

        <!-- Sport -->
        <UFormField label="Sport">
          <USelect
            v-model="selectedSport"
            :items="sportOptions"
            value-key="value"
            label-key="label"
            class="w-full"
            placeholder="Select sport"
          />
        </UFormField>

        <!-- Session type -->
        <UFormField label="Session Type">
          <div class="flex gap-2">
            <button
              type="button"
              :class="[
                'flex flex-1 items-center justify-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-medium transition-colors',
                sessionType === 'private'
                  ? 'border-[var(--aktiv-primary)] bg-[var(--aktiv-primary)] text-white'
                  : 'border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] text-[var(--aktiv-ink)] hover:bg-[var(--aktiv-border)]'
              ]"
              @click="sessionType = 'private'"
            >
              <UIcon name="i-heroicons-lock-closed" class="h-4 w-4" />
              Private
            </button>
            <button
              type="button"
              :class="[
                'flex flex-1 items-center justify-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-medium transition-colors',
                sessionType === 'open_play'
                  ? 'border-[var(--aktiv-primary)] bg-[var(--aktiv-primary)] text-white'
                  : 'border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] text-[var(--aktiv-ink)] hover:bg-[var(--aktiv-border)]'
              ]"
              @click="sessionType = 'open_play'"
            >
              <UIcon name="i-heroicons-user-group" class="h-4 w-4" />
              Open Play
            </button>
          </div>
          <p class="mt-1.5 text-xs text-[var(--aktiv-muted)]">
            <template v-if="sessionType === 'private'">
              Private session — you and your group have exclusive access to the
              court.
            </template>
            <template v-else>
              Open Play — anyone can discover and join this session. Price is
              per player.
            </template>
          </p>
        </UFormField>

        <!-- Price estimate -->
        <div
          class="flex items-center justify-between rounded-xl border border-[var(--aktiv-border)] bg-[var(--aktiv-background)] px-4 py-3"
        >
          <template v-if="sessionType === 'private'">
            <span class="text-sm text-[var(--aktiv-muted)]">
              Estimated total
              <span class="text-xs">({{ durationLabel }})</span>
            </span>
            <span class="text-lg font-black text-[var(--aktiv-ink)]">
              <template v-if="estimatedTotal != null"
                >₱{{ formatPrice(estimatedTotal) }}</template
              >
              <span v-else class="text-sm text-[var(--aktiv-muted)]"
                >Not set</span
              >
            </span>
          </template>
          <template v-else>
            <span class="text-sm text-[var(--aktiv-muted)]"
              >Price per player</span
            >
            <span class="text-lg font-black text-[var(--aktiv-ink)]">
              <template v-if="court?.open_play_price_per_head">
                ₱{{ formatPrice(parseFloat(court.open_play_price_per_head)) }}
              </template>
              <span v-else class="text-sm text-[var(--aktiv-muted)]"
                >Not set</span
              >
            </span>
          </template>
        </div>

        <!-- Submission error -->
        <UAlert
          v-if="submitError"
          color="error"
          variant="soft"
          :description="submitError"
        />

        <!-- Login prompt (shown when not authenticated) -->
        <UAlert
          v-if="!isLoggedIn"
          color="primary"
          variant="soft"
          icon="i-heroicons-lock-closed"
          title="Login required to book"
          description="You need to be signed in to complete a booking."
        />
      </div>

      <!-- Footer actions -->
      <div
        class="mt-6 flex justify-end gap-2 border-t border-[var(--aktiv-border)] pt-4"
      >
        <UButton color="neutral" variant="ghost" @click="isOpen = false">
          Cancel
        </UButton>
        <UButton v-if="!isLoggedIn" color="primary" @click="goToLogin">
          Sign in to book
        </UButton>
        <UButton
          v-else
          color="primary"
          :loading="isSubmitting"
          :disabled="!selectedSport"
          @click="handleConfirm"
        >
          Confirm Booking
        </UButton>
      </div>
    </template>
  </UModal>
</template>
