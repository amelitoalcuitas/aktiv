<script setup lang="ts">
import { z } from 'zod';
import { useAuth } from '~/composables/useAuth';

definePageMeta({ layout: 'auth' });

const { login } = useAuth();
const route = useRoute();

const resetSuccess = computed(() => route.query.reset === 'success');

// Safe redirect: only allow same-origin paths starting with /
const redirectPath = computed(() => {
  const r = route.query.redirect;
  return typeof r === 'string' && r.startsWith('/') ? r : '/dashboard';
});

const form = reactive({ email: '', password: '' });
const error = ref<string | null>(null);
const fieldErrors = ref<Record<string, string>>({});
const loading = ref(false);

const loginSchema = z.object({
  email: z.string().trim().min(1, 'Email is required.').email('Invalid email.'),
  password: z.string().min(1, 'Password is required.')
});

function fieldError(field: string) {
  return fieldErrors.value[field];
}

async function handleSubmit() {
  error.value = null;
  fieldErrors.value = {};

  const parsed = loginSchema.safeParse(form);
  if (!parsed.success) {
    for (const issue of parsed.error.issues) {
      const key = issue.path[0];
      if (typeof key === 'string' && !fieldErrors.value[key]) {
        fieldErrors.value[key] = issue.message;
      }
    }
    return;
  }

  loading.value = true;
  try {
    await login(parsed.data.email, parsed.data.password);
    await navigateTo(redirectPath.value);
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
      v-if="resetSuccess"
      color="success"
      variant="subtle"
      description="Your password has been reset. Sign in below."
      class="mb-4"
    />

    <UAlert
      v-if="error"
      color="error"
      variant="subtle"
      :description="error"
      class="mb-4"
    />

    <form class="space-y-4" @submit.prevent="handleSubmit">
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
          placeholder="••••••••"
          autocomplete="current-password"
          required
          class="w-full"
        />
      </UFormField>

      <div class="text-right">
        <NuxtLink
          to="/auth/forgot-password"
          class="text-sm text-[#004e89] hover:underline"
        >
          Forgot password?
        </NuxtLink>
      </div>

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
