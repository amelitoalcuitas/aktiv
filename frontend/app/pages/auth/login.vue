<script setup lang="ts">
import { z } from 'zod';
import { useAuth } from '~/composables/useAuth';

definePageMeta({ layout: 'auth', middleware: 'guest' });

interface GoogleAuthPopupMessage {
  type: 'aktiv:google-auth-result';
  status: 'success' | 'error' | 'needs_profile';
  token?: string;
  pendingToken?: string;
  redirect: string;
  reason?: string;
}

const { login, isSuperAdmin, getGoogleRedirectUrl, finalizeGoogleLogin } =
  useAuth();
const route = useRoute();
const toast = useToast();

const resetSuccess = computed(() => route.query.reset === 'success');
const setupSuccess = computed(() => route.query.setup === 'success');

// Safe redirect: only allow same-origin paths starting with /
const redirectPath = computed(() => {
  const r = route.query.redirect;
  if (typeof r === 'string' && r.startsWith('/')) return r;
  return isSuperAdmin.value ? '/panel' : '/dashboard';
});

const form = reactive({ email: String(route.query.email ?? ''), password: '' });
const error = ref<string | null>(null);
const fieldErrors = ref<Record<string, string>>({});
const loading = ref(false);
const googleLoading = ref(false);

const loginSchema = z.object({
  email: z.string().trim().min(1, 'Email is required.').email('Invalid email.'),
  password: z.string().min(1, 'Password is required.')
});

function fieldError(field: string) {
  return fieldErrors.value[field];
}

function sanitizeRedirect(value: unknown): string | null {
  return typeof value === 'string' &&
    value.startsWith('/') &&
    !value.startsWith('//')
    ? value
    : null;
}

function openGooglePopup(): Window | null {
  if (!import.meta.client) {
    return null;
  }

  const width = 520;
  const height = 640;
  const left = Math.max(
    0,
    Math.round(window.screenX + (window.outerWidth - width) / 2)
  );
  const top = Math.max(
    0,
    Math.round(window.screenY + (window.outerHeight - height) / 2)
  );

  return window.open(
    '',
    'aktiv-google-auth',
    `popup=yes,width=${width},height=${height},left=${left},top=${top}`
  );
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

async function handleGoogleLogin() {
  googleLoading.value = true;

  try {
    const redirect =
      sanitizeRedirect(route.query.redirect) ?? redirectPath.value;
    const popup = openGooglePopup();

    const url = await getGoogleRedirectUrl(redirect);

    if (!popup) {
      await navigateTo(url, { external: true });
      return;
    }

    const result = await new Promise<GoogleAuthPopupMessage>(
      (resolve, reject) => {
        const popupPoll = window.setInterval(() => {
          if (popup.closed) {
            window.clearInterval(popupPoll);
            window.removeEventListener('message', handleMessage);
            reject(new Error('popup_closed'));
          }
        }, 400);

        const handleMessage = (event: MessageEvent<GoogleAuthPopupMessage>) => {
          if (event.origin !== window.location.origin) {
            return;
          }

          if (event.data?.type !== 'aktiv:google-auth-result') {
            return;
          }

          window.clearInterval(popupPoll);
          window.removeEventListener('message', handleMessage);
          resolve(event.data);
        };

        window.addEventListener('message', handleMessage);
        popup.location.href = url;
        popup.focus();
      }
    );

    if (result.status === 'needs_profile' && result.pendingToken) {
      toast.add({
        title: 'Complete your location to finish signing in.',
        color: 'info'
      });
      await navigateTo(
        `/auth/google/complete?pending_token=${encodeURIComponent(result.pendingToken)}&redirect=${encodeURIComponent(result.redirect)}`
      );
      return;
    }

    if (result.status !== 'success' || !result.token) {
      throw new Error(result.reason ?? 'oauth_failed');
    }

    const user = await finalizeGoogleLogin(result.token);
    const destination =
      result.redirect === '/dashboard' && user.role === 'super_admin'
        ? '/panel'
        : result.redirect;

    toast.add({
      title: 'Signed in with Google.',
      color: 'success'
    });
    await navigateTo(destination);
  } catch {
    toast.add({
      title: 'Failed to sign in with Google.',
      color: 'error'
    });
  } finally {
    googleLoading.value = false;
  }
}
</script>

<template>
  <UCard :ui="{ root: 'ring-1 ring-[#dbe4ef]' }">
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
      v-if="setupSuccess"
      color="success"
      variant="subtle"
      description="Password set! You can now sign in."
      class="mb-4"
    />

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

      <USeparator label="OR" />

      <AuthGoogleAuthBtn :loading="googleLoading" @click="handleGoogleLogin" />
    </form>
  </UCard>
</template>
