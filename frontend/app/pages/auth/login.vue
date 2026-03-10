<script setup lang="ts">
import { useAuth } from '~/composables/useAuth';

definePageMeta({ layout: 'auth' });

const { login } = useAuth();

const form = reactive({ email: '', password: '' });
const error = ref<string | null>(null);
const loading = ref(false);

async function handleSubmit() {
  error.value = null;
  loading.value = true;
  try {
    await login(form.email, form.password);
    await navigateTo('/dashboard');
  } catch (e: unknown) {
    const err = e as { data?: { message?: string } };
    error.value = err?.data?.message ?? 'Invalid email or password.';
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <UCard :ui="{ root: 'shadow-lg ring-1 ring-[#dbe4ef]' }">
    <template #header>
      <h1 class="text-xl font-bold text-[#0f1728]">Sign in to Aktiv</h1>
      <p class="mt-1 text-sm text-[#64748b]">
        Don't have an account?
        <NuxtLink
          to="/auth/register"
          class="font-medium text-[#004e89] hover:underline"
        >
          Register
        </NuxtLink>
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
      <UFormField label="Email" name="email">
        <UInput
          v-model="form.email"
          type="email"
          placeholder="you@example.com"
          autocomplete="email"
          required
          class="w-full"
        />
      </UFormField>

      <UFormField label="Password" name="password">
        <UInput
          v-model="form.password"
          type="password"
          placeholder="••••••••"
          autocomplete="current-password"
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
        Sign In
      </UButton>
    </form>
  </UCard>
</template>
