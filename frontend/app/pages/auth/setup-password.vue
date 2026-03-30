<script setup lang="ts">
import { z } from 'zod';
import { useAuth } from '~/composables/useAuth';

definePageMeta({ layout: 'auth', middleware: 'guest' });

const { setupPassword } = useAuth();
const route = useRoute();

const token = computed(() => String(route.query.token ?? ''));
const email = computed(() => String(route.query.email ?? ''));

const form = reactive({ password: '', password_confirmation: '' });
const error = ref<string | null>(null);
const fieldErrors = ref<Record<string, string>>({});
const loading = ref(false);

const schema = z
  .object({
    password: z.string().min(8, 'Password must be at least 8 characters.'),
    password_confirmation: z.string().min(1, 'Please confirm your password.')
  })
  .refine((d) => d.password === d.password_confirmation, {
    message: 'Passwords do not match.',
    path: ['password_confirmation']
  });

async function handleSubmit() {
  error.value = null;
  fieldErrors.value = {};

  const parsed = schema.safeParse(form);
  if (!parsed.success) {
    for (const issue of parsed.error.issues) {
      const key = issue.path[0];
      if (typeof key === 'string' && !fieldErrors.value[key]) {
        fieldErrors.value[key] = issue.message;
      }
    }
    return;
  }

  if (!token.value || !email.value) {
    error.value = 'Invalid or missing setup link. Please contact an administrator.';
    return;
  }

  loading.value = true;
  try {
    await setupPassword(
      token.value,
      email.value,
      parsed.data.password,
      parsed.data.password_confirmation
    );
    await navigateTo(`/auth/login?setup=success&email=${encodeURIComponent(email.value)}`);
  } catch (e: unknown) {
    const err = e as { data?: { message?: string } };
    error.value =
      err?.data?.message ?? 'This setup link is invalid or has expired. Please contact an administrator.';
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <UCard :ui="{ root: 'ring-1 ring-[#dbe4ef]' }">
    <template #header>
      <h1 class="text-xl font-bold text-[#0f1728]">Set up your password</h1>
      <p class="mt-1 text-sm text-[#64748b]">
        Welcome to Aktiv! Create a password to access your account.
      </p>
    </template>

    <UAlert
      v-if="error"
      color="error"
      variant="subtle"
      :description="error"
      class="mb-4"
    />

    <form class="space-y-4" @submit.prevent="handleSubmit">
      <UFormField
        label="Password"
        name="password"
        :error="fieldErrors['password']"
      >
        <UInput
          v-model="form.password"
          type="password"
          placeholder="••••••••"
          autocomplete="new-password"
          required
          class="w-full"
        />
      </UFormField>

      <UFormField
        label="Confirm password"
        name="password_confirmation"
        :error="fieldErrors['password_confirmation']"
      >
        <UInput
          v-model="form.password_confirmation"
          type="password"
          placeholder="••••••••"
          autocomplete="new-password"
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
        Set password
      </UButton>
    </form>
  </UCard>
</template>
