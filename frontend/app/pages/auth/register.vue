<script setup lang="ts">
import { useAuth } from '~/composables/useAuth';

definePageMeta({ layout: 'auth' });

const { register } = useAuth();

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: ''
});
const error = ref<string | null>(null);
const fieldErrors = ref<Record<string, string[]>>({});
const loading = ref(false);

async function handleSubmit() {
  error.value = null;
  fieldErrors.value = {};
  loading.value = true;
  try {
    await register(
      form.name,
      form.email,
      form.password,
      form.password_confirmation
    );
    await navigateTo('/dashboard');
  } catch (e: unknown) {
    const err = e as {
      data?: { message?: string; errors?: Record<string, string[]> };
    };
    error.value =
      err?.data?.message ?? 'Registration failed. Please check the form.';
    fieldErrors.value = err?.data?.errors ?? {};
  } finally {
    loading.value = false;
  }
}

function fieldError(field: string) {
  return fieldErrors.value[field]?.[0];
}
</script>

<template>
  <UCard :ui="{ root: 'shadow-lg ring-1 ring-[#dbe4ef]' }">
    <template #header>
      <h1 class="text-xl font-bold text-[#0f1728]">Create your account</h1>
      <p class="mt-1 text-sm text-[#64748b]">
        Already have an account?
        <NuxtLink
          to="/auth/login"
          class="font-medium text-[#004e89] hover:underline"
        >
          Sign in
        </NuxtLink>
      </p>
    </template>

    <UAlert
      v-if="error && !Object.keys(fieldErrors).length"
      color="error"
      variant="subtle"
      :description="error"
      class="mb-4"
    />

    <form class="space-y-4" @submit.prevent="handleSubmit">
      <UFormField label="Name" name="name" :error="fieldError('name')">
        <UInput
          v-model="form.name"
          placeholder="Your full name"
          autocomplete="name"
          required
          class="w-full"
        />
      </UFormField>

      <UFormField label="Email" name="email" :error="fieldError('email')">
        <UInput
          v-model="form.email"
          type="email"
          placeholder="you@example.com"
          autocomplete="email"
          required
          class="w-full"
        />
      </UFormField>

      <UFormField
        label="Password"
        name="password"
        :error="fieldError('password')"
      >
        <UInput
          v-model="form.password"
          type="password"
          placeholder="Min 8 characters"
          autocomplete="new-password"
          required
          class="w-full"
        />
      </UFormField>

      <UFormField label="Confirm Password" name="password_confirmation">
        <UInput
          v-model="form.password_confirmation"
          type="password"
          placeholder="Repeat password"
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
        Create Account
      </UButton>
    </form>
  </UCard>
</template>
