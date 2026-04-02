<script setup lang="ts">
import type { Hub } from '~/types/hub';
import type { OpenPlayParticipant, OpenPlaySession } from '~/types/openPlay';
import { useAuthStore } from '~/stores/auth';
import OpenPlayReceiptUploadModal from '~/components/openPlay/OpenplayReceiptUploadModal.vue';
import {
  getOpenPlayParticipantPresentation,
  getOpenPlaySessionPresentation
} from '~/utils/openPlayPresentation';

const props = defineProps<{
  open: boolean;
  hubId: string;
  hub: Hub | null | undefined;
  session: OpenPlaySession | null;
}>();

const emit = defineEmits<{
  'update:open': [boolean];
  'updated': [];
}>();

const authStore = useAuthStore();
const toast = useToast();
const { sendGuestVerificationCode, joinSession, leaveSession } = useOpenPlay();

const isOpen = computed({
  get: () => props.open,
  set: (value) => emit('update:open', value)
});

const isAuthenticated = computed(() => authStore.isAuthenticated);
const localParticipant = ref<OpenPlayParticipant | null>(null);
const currentParticipant = computed(
  () => localParticipant.value ?? props.session?.viewer_participant ?? null
);
const isFree = computed(
  () => Number(props.session?.price_per_player ?? '0') === 0
);
const canUploadReceipt = computed(
  () =>
    currentParticipant.value?.payment_status === 'pending_payment' &&
    currentParticipant.value?.payment_method === 'digital_bank'
);
const canLeaveSession = computed(
  () =>
    isAuthenticated.value &&
    (currentParticipant.value?.payment_status === 'pending_payment' ||
      currentParticipant.value?.payment_status === 'payment_sent')
);

const isReceiptModalOpen = ref(false);
const step = ref<'details' | 'verify' | 'confirm'>('details');
const selectedPaymentMethod = ref<'pay_on_site' | 'digital_bank' | null>(null);
const guestName = ref('');
const guestEmail = ref('');
const guestPhone = ref('');
const otp = ref('');
const actionError = ref<string | null>(null);
const sendingCode = ref(false);
const joining = ref(false);
const leaving = ref(false);
const joinDisabled = computed(
  () => props.session?.status === 'full' && !currentParticipant.value
);
const selectedPaymentLabel = computed(() => {
  if (isFree.value) return 'Free session';
  if (selectedPaymentMethod.value === 'digital_bank')
    return 'Digital bank transfer';
  if (selectedPaymentMethod.value === 'pay_on_site') return 'Pay on site';
  return 'Not selected';
});
const participantIdentityLabel = computed(() => {
  if (isAuthenticated.value) {
    const fullName = [authStore.user?.first_name, authStore.user?.last_name]
      .filter(Boolean)
      .join(' ');

    return fullName || authStore.user?.email || 'Signed-in Aktiv account';
  }

  return guestName.value || guestEmail.value || 'Guest participant';
});
const participantIdentityDetail = computed(() => {
  if (isAuthenticated.value) return authStore.user?.email || null;
  return (
    [guestEmail.value, guestPhone.value].filter(Boolean).join(' · ') || null
  );
});
const confirmStepDescription = computed(() => {
  if (isFree.value) return 'You will join immediately.';
  if (selectedPaymentMethod.value === 'digital_bank') {
    return "Your spot will be created and you'll upload a receipt next.";
  }

  return 'Your join will be created as pending venue confirmation and may still be reviewed by the venue.';
});
const currentParticipantPresentation = computed(() =>
  currentParticipant.value
    ? getOpenPlayParticipantPresentation(currentParticipant.value)
    : null
);
const sessionPresentation = computed(() =>
  props.session ? getOpenPlaySessionPresentation(props.session) : null
);

watch(
  () => [props.open, props.hub?.payment_methods],
  ([open, paymentMethods]) => {
    if (!open) return;

    actionError.value = null;
    step.value = 'details';
    otp.value = '';
    localParticipant.value = null;
    guestName.value = '';
    guestEmail.value = '';
    guestPhone.value = '';
    selectedPaymentMethod.value =
      (paymentMethods?.[0] as 'pay_on_site' | 'digital_bank' | undefined) ??
      'pay_on_site';
  },
  { immediate: true }
);

watch(
  () => props.session?.id,
  () => {
    actionError.value = null;
    otp.value = '';
    step.value = 'details';
    localParticipant.value = null;
  }
);

function formatPrice(price: string): string {
  const n = Number(price);
  if (Number.isNaN(n)) return price;
  return n.toLocaleString('en-PH', {
    minimumFractionDigits: n % 1 === 0 ? 0 : 2,
    maximumFractionDigits: 2
  });
}

function formatSessionDate(session: OpenPlaySession): string {
  if (!session.booking) return '';

  const start = new Date(session.booking.start_time);
  const end = new Date(session.booking.end_time);

  return `${start.toLocaleDateString('en-PH', {
    timeZone: 'Asia/Manila',
    weekday: 'short',
    month: 'short',
    day: 'numeric'
  })} · ${start.toLocaleTimeString('en-PH', {
    timeZone: 'Asia/Manila',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  })} – ${end.toLocaleTimeString('en-PH', {
    timeZone: 'Asia/Manila',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  })}`;
}

function closeJoinModal() {
  isOpen.value = false;
  actionError.value = null;
}

function closeModal() {
  isReceiptModalOpen.value = false;
  closeJoinModal();
}

function handleReceiptUploaded() {
  emit('updated');
  closeModal();
}

function goToConfirmStep() {
  actionError.value = null;
  step.value = 'confirm';
}

async function handleContinueFromDetails() {
  if (isAuthenticated.value) {
    goToConfirmStep();
    return;
  }

  await handleSendCode();
}

async function handleSendCode() {
  if (!props.session || !guestEmail.value) return;

  sendingCode.value = true;
  actionError.value = null;

  try {
    await sendGuestVerificationCode(
      props.hubId,
      props.session.id,
      guestEmail.value
    );
    step.value = 'verify';
    toast.add({
      title: 'Verification code sent',
      description: 'Check your email for the 6-digit code.',
      color: 'success'
    });
  } catch (err: unknown) {
    actionError.value =
      (err as { data?: { message?: string } })?.data?.message ??
      'Failed to send code. Please try again.';
    toast.add({ title: actionError.value!, color: 'error' });
  } finally {
    sendingCode.value = false;
  }
}

function handleContinueFromVerify() {
  if (otp.value.length !== 6) return;
  goToConfirmStep();
}

async function handleConfirmJoin() {
  if (!props.session || !selectedPaymentMethod.value) return;

  joining.value = true;
  actionError.value = null;

  try {
    const participant = await joinSession(props.hubId, props.session.id, {
      payment_method: selectedPaymentMethod.value,
      guest_name: isAuthenticated.value ? undefined : guestName.value,
      guest_phone: isAuthenticated.value ? undefined : guestPhone.value,
      guest_email: isAuthenticated.value ? undefined : guestEmail.value,
      otp: isAuthenticated.value ? undefined : otp.value
    });

    if (participant.payment_status === 'confirmed') {
      toast.add({
        title: 'Spot confirmed',
        color: 'success'
      });
      closeModal();
      emit('updated');
      return;
    }

    if (participant.payment_method === 'digital_bank') {
      localParticipant.value = participant;
      emit('updated');
      toast.add({
        title: 'Spot reserved',
        description:
          'Your status is now Awaiting Receipt. Upload your receipt next so the hub can review it.',
        color: 'success'
      });
      closeJoinModal();
      isReceiptModalOpen.value = true;
      return;
    }

    toast.add({
      title: 'Join submitted',
      description:
        'Your status is now Pending Venue Confirmation. The venue may still review your join before the session starts.',
      color: 'success'
    });
    closeModal();
    emit('updated');
  } catch (err: unknown) {
    actionError.value =
      (err as { data?: { message?: string } })?.data?.message ??
      'Failed to join this session.';
    toast.add({ title: actionError.value!, color: 'error' });
  } finally {
    joining.value = false;
  }
}

async function handleLeave() {
  if (!props.session) return;

  leaving.value = true;
  actionError.value = null;

  try {
    await leaveSession(props.hubId, props.session.id);
    localParticipant.value = null;
    toast.add({
      title: 'You left the session',
      color: 'success'
    });
    emit('updated');
    closeModal();
  } catch (err: unknown) {
    actionError.value =
      (err as { data?: { message?: string } })?.data?.message ??
      'Failed to leave the session.';
    toast.add({ title: actionError.value!, color: 'error' });
  } finally {
    leaving.value = false;
  }
}

function goBack() {
  actionError.value = null;

  if (step.value === 'confirm') {
    step.value = isAuthenticated.value ? 'details' : 'verify';
    return;
  }

  if (step.value === 'verify') {
    step.value = 'details';
  }
}
</script>

<template>
  <AppModal
    v-model:open="isOpen"
    title="Join Open Play"
    :ui="{ content: 'max-w-lg' }"
  >
    <template #body>
      <div v-if="session" class="space-y-4">
        <div
          class="rounded-xl border border-[var(--aktiv-border)] bg-[var(--aktiv-background)] p-4"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <h3 class="text-base font-bold text-[var(--aktiv-ink)]">
                Open Play
              </h3>
              <p class="mt-1 text-sm text-[var(--aktiv-muted)]">
                {{ session.booking?.court?.name ?? 'Court' }} ·
                {{ formatSessionDate(session) }}
              </p>
            </div>
            <UBadge
              :color="sessionPresentation?.color ?? 'primary'"
              variant="soft"
            >
              {{ sessionPresentation?.label ?? 'Open' }}
            </UBadge>
          </div>

          <div
            class="mt-3 flex flex-wrap gap-2 text-sm text-[var(--aktiv-muted)]"
          >
            <span class="rounded-md bg-[var(--aktiv-surface)] px-2.5 py-1">
              {{ session.participants_count }} /
              {{ session.max_players }} players
            </span>
            <span class="rounded-md bg-[var(--aktiv-surface)] px-2.5 py-1">
              {{
                isFree
                  ? 'Free session'
                  : `P${formatPrice(session.price_per_player)} / player`
              }}
            </span>
            <span
              v-if="session.guests_can_join"
              class="rounded-md bg-[var(--aktiv-surface)] px-2.5 py-1"
            >
              Guests allowed
            </span>
          </div>

          <p v-if="session.notes" class="mt-3 text-sm text-[var(--aktiv-ink)]">
            {{ session.notes }}
          </p>
          <p class="mt-3 text-sm text-[var(--aktiv-muted)]">
            {{ sessionPresentation?.helperText }}
          </p>
        </div>

        <div
          v-if="currentParticipant"
          class="rounded-xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-4"
        >
          <div class="flex items-center gap-2">
            <UIcon
              name="i-heroicons-user-circle"
              class="h-5 w-5 text-[var(--aktiv-primary)]"
            />
            <p class="text-sm font-semibold text-[var(--aktiv-ink)]">
              {{ currentParticipantPresentation?.label }}
            </p>
          </div>
          <p
            v-if="currentParticipantPresentation?.helperText"
            class="mt-2 text-sm text-[var(--aktiv-muted)]"
          >
            {{ currentParticipantPresentation.helperText }}
          </p>

          <p
            v-if="currentParticipant.payment_note"
            class="mt-2 text-sm text-red-600"
          >
            {{ currentParticipant.payment_note }}
          </p>

          <p
            v-if="
              currentParticipant.expires_at &&
              currentParticipant.payment_status === 'pending_payment'
            "
            class="mt-2 text-sm text-[var(--aktiv-muted)]"
          >
            Expires
            {{
              new Date(currentParticipant.expires_at).toLocaleString('en-PH', {
                timeZone: 'Asia/Manila',
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
              })
            }}
          </p>
        </div>

        <div
          v-else-if="joinDisabled"
          class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800"
        >
          <p class="font-semibold text-amber-900">Session full</p>
          <p class="mt-1">{{ sessionPresentation?.helperText }}</p>
        </div>

        <template v-else>
          <BookingPaymentMethodSelector
            v-if="!isFree"
            v-model="selectedPaymentMethod"
            :hub="hub"
          />

          <UAlert
            v-if="!isAuthenticated && !session.guests_can_join"
            color="warning"
            variant="soft"
            title="Sign in required"
            description="This session only accepts registered Aktiv users."
          />

          <template v-else-if="!isAuthenticated">
            <template v-if="step === 'details'">
              <div class="space-y-3">
                <UFormField label="Full Name" required>
                  <UInput
                    v-model="guestName"
                    placeholder="Juan dela Cruz"
                    class="w-full"
                  />
                </UFormField>

                <UFormField label="Email Address" required>
                  <UInput
                    v-model="guestEmail"
                    type="email"
                    placeholder="you@example.com"
                    class="w-full"
                  />
                </UFormField>

                <UFormField label="Phone" required>
                  <UInput
                    v-model="guestPhone"
                    type="tel"
                    placeholder="+63 917 000 0000"
                    class="w-full"
                  />
                </UFormField>

                <UAlert
                  color="info"
                  variant="soft"
                  icon="i-heroicons-envelope"
                  description="We’ll send a 6-digit verification code to your email before you confirm this join."
                />
              </div>
            </template>

            <template v-else-if="step === 'verify'">
              <div class="space-y-3">
                <p class="text-sm text-[var(--aktiv-muted)]">
                  A 6-digit code was sent to
                  <strong class="text-[var(--aktiv-ink)]">{{
                    guestEmail
                  }}</strong
                  >.
                </p>

                <UFormField label="Verification Code" required>
                  <UInput
                    v-model="otp"
                    placeholder="000000"
                    maxlength="6"
                    class="w-full font-mono text-lg tracking-widest"
                  />
                </UFormField>

                <div
                  class="flex items-center gap-3 text-sm text-[var(--aktiv-muted)]"
                >
                  <button
                    type="button"
                    class="font-medium text-[#004e89] underline underline-offset-2"
                    :disabled="sendingCode"
                    @click="step = 'details'"
                  >
                    Change details
                  </button>
                  <span>·</span>
                  <button
                    type="button"
                    class="font-medium text-[#004e89] underline underline-offset-2"
                    :disabled="sendingCode"
                    @click="handleSendCode"
                  >
                    {{ sendingCode ? 'Sending…' : 'Resend code' }}
                  </button>
                </div>
              </div>
            </template>
          </template>

          <div
            v-if="step === 'confirm'"
            class="space-y-4 rounded-xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-4"
          >
            <div class="flex items-center gap-2">
              <UIcon
                name="i-heroicons-check-badge"
                class="h-5 w-5 text-[var(--aktiv-primary)]"
              />
              <p class="text-sm font-semibold text-[var(--aktiv-ink)]">
                Confirm your open play booking
              </p>
            </div>

            <div class="grid gap-3 text-sm sm:grid-cols-2">
              <div class="rounded-lg bg-[var(--aktiv-background)] p-3">
                <p
                  class="text-xs uppercase tracking-wide text-[var(--aktiv-muted)]"
                >
                  Court
                </p>
                <p class="mt-1 font-semibold text-[var(--aktiv-ink)]">
                  {{ session.booking?.court?.name ?? 'Court' }}
                </p>
              </div>

              <div class="rounded-lg bg-[var(--aktiv-background)] p-3">
                <p
                  class="text-xs uppercase tracking-wide text-[var(--aktiv-muted)]"
                >
                  Schedule
                </p>
                <p class="mt-1 font-semibold text-[var(--aktiv-ink)]">
                  {{ formatSessionDate(session) }}
                </p>
              </div>

              <div class="rounded-lg bg-[var(--aktiv-background)] p-3">
                <p
                  class="text-xs uppercase tracking-wide text-[var(--aktiv-muted)]"
                >
                  Price
                </p>
                <p class="mt-1 font-semibold text-[var(--aktiv-ink)]">
                  {{
                    isFree
                      ? 'Free session'
                      : `P${formatPrice(session.price_per_player)} / player`
                  }}
                </p>
              </div>

              <div class="rounded-lg bg-[var(--aktiv-background)] p-3">
                <p
                  class="text-xs uppercase tracking-wide text-[var(--aktiv-muted)]"
                >
                  Payment Method
                </p>
                <p class="mt-1 font-semibold text-[var(--aktiv-ink)]">
                  {{ selectedPaymentLabel }}
                </p>
              </div>
            </div>

            <div class="rounded-lg bg-[var(--aktiv-background)] p-3 text-sm">
              <p
                class="text-xs uppercase tracking-wide text-[var(--aktiv-muted)]"
              >
                Joining As
              </p>
              <p class="mt-1 font-semibold text-[var(--aktiv-ink)]">
                {{ participantIdentityLabel }}
              </p>
              <p
                v-if="participantIdentityDetail"
                class="mt-1 text-[var(--aktiv-muted)]"
              >
                {{ participantIdentityDetail }}
              </p>
            </div>

            <UAlert
              color="info"
              variant="soft"
              icon="i-heroicons-information-circle"
              title="What happens next"
              :description="confirmStepDescription"
            />
          </div>
        </template>

        <UAlert
          v-if="actionError"
          color="error"
          variant="soft"
          :title="actionError"
        />
      </div>
    </template>

    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton
          v-if="!currentParticipant && step !== 'details'"
          color="neutral"
          variant="ghost"
          @click="goBack"
        >
          Back
        </UButton>
        <UButton color="neutral" variant="ghost" @click="closeModal">
          {{ currentParticipant || step === 'details' ? 'Close' : 'Cancel' }}
        </UButton>

        <template v-if="currentParticipant">
          <UButton
            v-if="canUploadReceipt"
            color="primary"
            variant="soft"
            icon="i-heroicons-arrow-up-tray"
            @click="isReceiptModalOpen = true"
          >
            Upload Receipt
          </UButton>
          <UButton
            v-if="canLeaveSession"
            color="error"
            :loading="leaving"
            @click="handleLeave"
          >
            Leave Session
          </UButton>
        </template>

        <template v-else-if="isAuthenticated || session?.guests_can_join">
          <UButton
            v-if="step === 'details'"
            color="primary"
            :loading="!isAuthenticated ? sendingCode : false"
            :disabled="
              (!isAuthenticated &&
                (!guestName || !guestEmail || !guestPhone)) ||
              (!isFree && !selectedPaymentMethod)
            "
            @click="handleContinueFromDetails"
          >
            Continue
          </UButton>
          <UButton
            v-else-if="step === 'verify'"
            color="primary"
            :disabled="
              joinDisabled ||
              (!isFree && !selectedPaymentMethod) ||
              otp.length !== 6
            "
            @click="handleContinueFromVerify"
          >
            Continue
          </UButton>
          <UButton
            v-else
            color="primary"
            :loading="joining"
            :disabled="joinDisabled || (!isFree && !selectedPaymentMethod)"
            @click="handleConfirmJoin"
          >
            Confirm Join
          </UButton>
        </template>
      </div>
    </template>
  </AppModal>

  <OpenPlayReceiptUploadModal
    v-model:open="isReceiptModalOpen"
    :hub-id="hubId"
    :session="session"
    :participant="currentParticipant"
    @uploaded="handleReceiptUploaded"
  />
</template>
