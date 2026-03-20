<script setup lang="ts">
import { z } from 'zod';
import { useAuth } from '~/composables/useAuth';

definePageMeta({ layout: 'auth' });

const { forgotPassword } = useAuth();

const email = ref('');
const error = ref<string | null>(null);
const emailError = ref<string | null>(null);
const loading = ref(false);
const sent = ref(false);

const schema = z.object({
  email: z.string().trim().min(1, 'Email is required.').email('Invalid email.')
});

async function handleSubmit() {
  error.value = null;
  emailError.value = null;

  const parsed = schema.safeParse({ email: email.value });
  if (!parsed.success) {
    emailError.value = parsed.error.issues[0]?.message ?? 'Invalid email.';
    return;
  }

  loading.value = true;
  try {
    await forgotPassword(parsed.data.email);
    sent.value = true;
  } catch {
    error.value = 'Something went wrong. Please try again.';
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <UCard :ui="{ root: 'shadow-lg ring-1 ring-[#dbe4ef]' }">
    <template #header>
      <h1 class="text-xl font-bold text-[#0f1728]">Forgot password?</h1>
      <p class="mt-1 text-sm text-[#64748b]">
        Enter your email and we'll send you a reset link.
      </p>
    </template>

    <div v-if="sent" class="space-y-4">
      <UAlert
        color="success"
        variant="subtle"
        title="Check your inbox"
        description="If an account exists for that email, a password reset link has been sent. It expires in 60 minutes."
      />
      <p class="text-center text-sm text-[#64748b]">
        <NuxtLink to="/auth/login" class="font-medium text-[#004e89] hover:underline">
          Back to sign in
        </NuxtLink>
      </p>
    </div>

    <form v-else class="space-y-4" @submit.prevent="handleSubmit">
      <UAlert
        v-if="error"
        color="error"
        variant="subtle"
        :description="error"
      />

      <UFormField label="Email" name="email" :error="emailError ?? undefined">
        <UInput
          v-model="email"
          type="email"
          placeholder="you@example.com"
          autocomplete="email"
          required
          class="w-full"
        />
      </UFormField>

      <UButton
        type="submit"
        :loading="loading"
        block
        class="mt-2 bg-[#004e89] font-semibold hover:bg-[#003d6b]"
      >
        Send reset link
      </UButton>

      <p class="text-center text-sm text-[#64748b]">
        <NuxtLink to="/auth/login" class="font-medium text-[#004e89] hover:underline">
          Back to sign in
        </NuxtLink>
      </p>
    </form>
  </UCard>
</template>
