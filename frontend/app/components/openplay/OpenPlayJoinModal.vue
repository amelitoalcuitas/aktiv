<script setup lang="ts">
import type { Hub } from '~/types/hub';
import type { OpenPlayParticipant, OpenPlaySession } from '~/types/openPlay';
import { useAuthStore } from '~/stores/auth';
import OpenPlayReceiptUploadModal from '~/components/openplay/OpenPlayReceiptUploadModal.vue';

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
    (
      currentParticipant.value?.payment_status === 'pending_payment' ||
      currentParticipant.value?.payment_status === 'payment_sent'
    )
);

const isReceiptModalOpen = ref(false);
const step = ref<'details' | 'verify'>('details');
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

watch(
  () => [props.open, props.hub?.payment_methods],
  ([open, paymentMethods]) => {
    if (!open) return;

    actionError.value = null;
    step.value = 'details';
    otp.value = '';
    localParticipant.value = null;
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

function participantStatusLabel(participant: OpenPlayParticipant): string {
  switch (participant.payment_status) {
    case 'confirmed':
      return 'You are confirmed for this session.';
    case 'payment_sent':
      return 'Your receipt is under review.';
    case 'pending_payment':
      return participant.payment_method === 'digital_bank'
        ? 'Complete payment to confirm your spot.'
        : 'Your spot is pending venue confirmation.';
    case 'cancelled':
      return 'This join has been cancelled.';
  }
}

function closeModal() {
  isReceiptModalOpen.value = false;
  isOpen.value = false;
  actionError.value = null;
}

function handleReceiptUploaded() {
  emit('updated');
  closeModal();
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

async function handleJoin() {
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
        title: 'You joined the session!',
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
        title: 'Join created',
        description: 'Upload your payment receipt to confirm your spot.',
        color: 'success'
      });
      isReceiptModalOpen.value = true;
      return;
    }

    toast.add({
      title: 'Join created',
      description:
        'Show up at the venue to complete your payment confirmation.',
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
              :color="session.status === 'full' ? 'warning' : 'primary'"
              variant="soft"
            >
              {{ session.participants_count }} / {{ session.max_players }}
            </UBadge>
          </div>

          <div
            class="mt-3 flex flex-wrap gap-2 text-sm text-[var(--aktiv-muted)]"
          >
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
              {{ participantStatusLabel(currentParticipant) }}
            </p>
          </div>

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
                  description="We’ll send a 6-digit verification code to your email before creating your join."
                />
              </div>
            </template>

            <template v-else>
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
        <UButton color="neutral" variant="ghost" @click="closeModal">
          Close
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
            v-if="!isAuthenticated && step === 'details'"
            color="primary"
            :loading="sendingCode"
            :disabled="
              !guestName ||
              !guestEmail ||
              !guestPhone ||
              (!isFree && !selectedPaymentMethod)
            "
            @click="handleSendCode"
          >
            Send Verification Code
          </UButton>
          <UButton
            v-else
            color="primary"
            :loading="joining"
            :disabled="
              joinDisabled ||
              (!isFree && !selectedPaymentMethod) ||
              (!isAuthenticated && step === 'verify' && otp.length !== 6)
            "
            @click="handleJoin"
          >
            {{ isFree ? 'Join Session' : 'Join & Continue' }}
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
