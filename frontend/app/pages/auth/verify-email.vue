<script setup lang="ts">
import { useAuth } from '~/composables/useAuth';

definePageMeta({ layout: 'auth', middleware: 'auth' });

const { user, resendVerification, logout } = useAuth();
const toast = useToast();
const resending = ref(false);

async function handleResend() {
  resending.value = true;
  try {
    await resendVerification();
    toast.add({
      title: 'Email sent',
      description: 'A new verification link has been sent to your inbox.',
      color: 'success'
    });
  } catch (err: any) {
    const is429 = err?.response?.status === 429 || err?.status === 429;
    if (is429) {
      const retryAfter = Number(err?.response?.headers?.get('Retry-After') ?? err?.response?.headers?.['retry-after'] ?? 0);
      const mins = retryAfter > 0 ? Math.ceil(retryAfter / 60) : 5;
      toast.add({
        title: 'Please wait',
        description: `You can resend again in ${mins} minute${mins !== 1 ? 's' : ''}. Please check your inbox.`,
        color: 'error'
      });
    } else {
      toast.add({
        title: 'Error',
        description: 'Could not resend the verification email. Please try again.',
        color: 'error'
      });
    }
  } finally {
    resending.value = false;
  }
}
</script>

<template>
  <UCard :ui="{ root: 'shadow-lg ring-1 ring-[#dbe4ef]' }">
    <template #header>
      <h1 class="text-xl font-bold text-[#0f1728]">Verify your email</h1>
      <p class="mt-1 text-sm text-[#64748b]">
        We sent a verification link to
        <span class="font-medium text-[#0f1728]">{{ user?.email }}</span>.
        Please check your inbox.
      </p>
    </template>

    <div class="space-y-3">
      <UButton
        block
        :loading="resending"
        class="bg-[#004e89] font-semibold hover:bg-[#003d6b]"
        @click="handleResend"
      >
        Resend verification email
      </UButton>

      <UButton
        block
        variant="ghost"
        color="neutral"
        @click="logout"
      >
        Log out
      </UButton>
    </div>
  </UCard>
</template>
