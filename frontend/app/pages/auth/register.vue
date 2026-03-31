<script setup lang="ts">
import { z } from 'zod';
import { COUNTRY_OPTIONS } from '~/constants/countries';
import { useAuth } from '~/composables/useAuth';

definePageMeta({ layout: 'auth', middleware: 'guest' });

interface GoogleAuthPopupMessage {
  type: 'aktiv:google-auth-result';
  status: 'success' | 'error';
  token?: string;
  redirect: string;
  reason?: string;
}

const { register, getGoogleRedirectUrl, finalizeGoogleLogin } = useAuth();
const route = useRoute();
const toast = useToast();

const form = reactive({
  first_name: '',
  last_name: '',
  email: '',
  country: '',
  province: '',
  city: '',
  password: '',
  password_confirmation: ''
});
const error = ref<string | null>(null);
const validationErrors = ref<Record<string, string>>({});
const serverFieldErrors = ref<Record<string, string[]>>({});
const loading = ref(false);
const googleLoading = ref(false);

const registerSchema = z
  .object({
    first_name: z.string().trim().min(1, 'First name is required.'),
    last_name: z.string().trim().min(1, 'Last name is required.'),
    email: z
      .string()
      .trim()
      .min(1, 'Email is required.')
      .email('Invalid email.'),
    country: z.string().trim().min(1, 'Country is required.'),
    province: z.string().trim().min(1, 'Province is required.'),
    city: z.string().trim().min(1, 'City is required.'),
    password: z.string().min(8, 'Password must be at least 8 characters.'),
    password_confirmation: z.string().min(1, 'Please confirm your password.')
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: 'Passwords do not match.',
    path: ['password_confirmation']
  });

async function handleSubmit() {
  error.value = null;
  validationErrors.value = {};
  serverFieldErrors.value = {};

  const parsed = registerSchema.safeParse(form);
  if (!parsed.success) {
    for (const issue of parsed.error.issues) {
      const key = issue.path[0];
      if (typeof key === 'string' && !validationErrors.value[key]) {
        validationErrors.value[key] = issue.message;
      }
    }
    return;
  }

  loading.value = true;
  try {
    await register(
      parsed.data.first_name,
      parsed.data.last_name,
      parsed.data.email,
      parsed.data.country,
      parsed.data.province,
      parsed.data.city,
      parsed.data.password,
      parsed.data.password_confirmation
    );
    await navigateTo('/auth/verify-email');
  } catch (e: unknown) {
    const err = e as {
      data?: { message?: string; errors?: Record<string, string[]> };
    };
    error.value =
      err?.data?.message ?? 'Registration failed. Please check the form.';
    serverFieldErrors.value = err?.data?.errors ?? {};
  } finally {
    loading.value = false;
  }
}

function fieldError(field: string) {
  return validationErrors.value[field] ?? serverFieldErrors.value[field]?.[0];
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

async function handleGoogleRegister() {
  googleLoading.value = true;

  try {
    const redirect = sanitizeRedirect(route.query.redirect) ?? '/dashboard';
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
      v-if="
        error &&
        !Object.keys(serverFieldErrors).length &&
        !Object.keys(validationErrors).length
      "
      color="error"
      variant="subtle"
      :description="error"
      class="mb-4"
    />

    <form class="space-y-4" @submit.prevent="handleSubmit">
      <div class="grid grid-cols-2 gap-3">
        <UFormField
          label="First Name"
          name="first_name"
          :error="fieldError('first_name')"
        >
          <UInput
            v-model="form.first_name"
            placeholder="Enter first name"
            autocomplete="given-name"
            required
            class="w-full"
          />
        </UFormField>

        <UFormField
          label="Last Name"
          name="last_name"
          :error="fieldError('last_name')"
        >
          <UInput
            v-model="form.last_name"
            placeholder="Enter last name"
            autocomplete="family-name"
            required
            class="w-full"
          />
        </UFormField>
      </div>

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

      <UFormField label="Country" name="country" :error="fieldError('country')">
        <USelectMenu
          v-model="form.country"
          :items="COUNTRY_OPTIONS"
          value-key="value"
          label-key="label"
          placeholder="Select country"
          required
          class="w-full"
        />
      </UFormField>

      <div class="grid grid-cols-2 gap-3">
        <UFormField
          label="Province"
          name="province"
          :error="fieldError('province')"
        >
          <UInput
            v-model="form.province"
            placeholder="Enter province"
            required
            class="w-full"
          />
        </UFormField>

        <UFormField label="City" name="city" :error="fieldError('city')">
          <UInput
            v-model="form.city"
            placeholder="Enter city"
            required
            class="w-full"
          />
        </UFormField>
      </div>

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

      <USeparator label="OR" />

      <AuthGoogleAuthBtn
        :loading="googleLoading"
        @click="handleGoogleRegister"
      />
    </form>
  </UCard>
</template>
