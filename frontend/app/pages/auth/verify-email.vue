<script setup lang="ts">
import { useAuth } from '~/composables/useAuth';

definePageMeta({ layout: 'auth', middleware: 'auth' });

const { user, resendVerification, resendVerificationStatus, logout } = useAuth();
const toast = useToast();
const resending = ref(false);
const { remainingSeconds, isCoolingDown, sync } = useCooldownTimer();

const resendLabel = computed(() => {
  if (!isCoolingDown.value) return 'Resend verification email';

  const minutes = Math.floor(remainingSeconds.value / 60);
  const seconds = String(remainingSeconds.value % 60).padStart(2, '0');

  return `Resend in ${minutes}:${seconds}`;
});

onMounted(async () => {
  try {
    const response = await resendVerificationStatus();
    sync(response.cooldown);
  } catch {
    sync(null);
  }
});

async function handleResend() {
  resending.value = true;
  try {
    const response = await resendVerification();
    sync(response.cooldown);
    toast.add({
      title: 'Email sent',
      description: 'A new verification link has been sent to your inbox.',
      color: 'success'
    });
  } catch (err: any) {
    sync(err?.data?.cooldown ?? null);

    const is429 = err?.response?.status === 429 || err?.status === 429;
    if (is429) {
      const retryAfter = Number(
        err?.data?.cooldown?.remaining_seconds ??
        err?.response?.headers?.get?.('Retry-After') ??
          err?.response?.headers?.['retry-after'] ??
          0
      );
      const mins = retryAfter > 0 ? Math.ceil(retryAfter / 60) : 5;
      toast.add({
        title: 'Please wait',
        description: `You can resend again in ${mins} minute${mins !== 1 ? 's' : ''}. Please check your inbox.`,
        color: 'error'
      });
    } else {
      toast.add({
        title: 'Error',
        description:
          'Could not resend the verification email. Please try again.',
        color: 'error'
      });
    }
  } finally {
    resending.value = false;
  }
}
</script>

<template>
  <UCard :ui="{ root: 'ring-1 ring-[#dbe4ef]' }">
    <template #header>
      <h1 class="text-xl font-bold text-[#0f1728]">Verify your email</h1>
      <p class="mt-1 text-sm text-[#64748b]">
        We sent a verification link to
        <span class="font-medium text-[#0f1728]">{{ user?.email }}</span
        >. Please check your inbox.
      </p>
    </template>

    <div class="space-y-3">
      <UButton
        block
        :loading="resending"
        :disabled="isCoolingDown"
        class="bg-[#004e89] font-semibold hover:bg-[#003d6b]"
        @click="handleResend"
      >
        {{ resendLabel }}
      </UButton>

      <UButton block variant="ghost" color="neutral" @click="logout">
        Log out
      </UButton>
    </div>
  </UCard>
</template>
