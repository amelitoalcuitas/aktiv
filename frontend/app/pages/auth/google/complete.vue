<script setup lang="ts">
import { z } from 'zod';
import { COUNTRY_OPTIONS } from '~/constants/countries';
import { useAuth } from '~/composables/useAuth';

definePageMeta({ layout: 'auth', middleware: 'guest' });

const { completeGoogleSignup } = useAuth();
const route = useRoute();
const toast = useToast();

const form = reactive({
  country: '',
  province: '',
  city: ''
});

const error = ref<string | null>(null);
const validationErrors = ref<Record<string, string>>({});
const serverFieldErrors = ref<Record<string, string[]>>({});
const loading = ref(false);

const completeSchema = z.object({
  country: z.string().trim().min(1, 'Country is required.'),
  province: z.string().trim().min(1, 'Province is required.'),
  city: z.string().trim().min(1, 'City is required.')
});

function sanitizeRedirect(value: unknown): string {
  return typeof value === 'string' &&
    value.startsWith('/') &&
    !value.startsWith('//')
    ? value
    : '/dashboard';
}

const pendingToken = computed(() =>
  typeof route.query.pending_token === 'string' ? route.query.pending_token : ''
);

const redirectPath = computed(() => sanitizeRedirect(route.query.redirect));

function fieldError(field: string) {
  return validationErrors.value[field] ?? serverFieldErrors.value[field]?.[0];
}

async function handleSubmit() {
  error.value = null;
  validationErrors.value = {};
  serverFieldErrors.value = {};

  const parsed = completeSchema.safeParse(form);
  if (!parsed.success) {
    for (const issue of parsed.error.issues) {
      const key = issue.path[0];
      if (typeof key === 'string' && !validationErrors.value[key]) {
        validationErrors.value[key] = issue.message;
      }
    }
    return;
  }

  if (!pendingToken.value) {
    toast.add({
      title: 'Your Google sign-in session expired.',
      color: 'error'
    });
    await navigateTo('/auth/login', { replace: true });
    return;
  }

  loading.value = true;
  try {
    const user = await completeGoogleSignup(
      pendingToken.value,
      parsed.data.country,
      parsed.data.province,
      parsed.data.city
    );

    const destination =
      redirectPath.value === '/dashboard' && user.role === 'super_admin'
        ? '/panel'
        : redirectPath.value;

    toast.add({
      title: 'Google account completed.',
      color: 'success'
    });
    await navigateTo(destination);
  } catch (e: unknown) {
    const err = e as {
      data?: { message?: string; errors?: Record<string, string[]> };
    };

    error.value =
      err?.data?.message ?? 'Failed to complete your Google sign-in.';
    serverFieldErrors.value = err?.data?.errors ?? {};

    toast.add({
      title: 'Failed to complete your Google sign-in.',
      color: 'error'
    });
  } finally {
    loading.value = false;
  }
}

onMounted(async () => {
  if (pendingToken.value) {
    return;
  }

  toast.add({
    title: 'Your Google sign-in session expired.',
    color: 'error'
  });
  await navigateTo('/auth/login', { replace: true });
});
</script>

<template>
  <UCard :ui="{ root: 'ring-1 ring-[#dbe4ef]' }">
    <template #header>
      <h1 class="text-xl font-bold text-[#0f1728]">Complete your profile</h1>
      <p class="mt-1 text-sm text-[#64748b]">
        We still need your location to finish creating your Google account.
      </p>
    </template>

    <UAlert
      v-if="error || !pendingToken"
      color="error"
      variant="subtle"
      :description="error || 'Your Google sign-in session expired. Please sign in again.'"
      class="mb-4"
    />

    <form class="space-y-4" @submit.prevent="handleSubmit">
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

      <UButton
        type="submit"
        :loading="loading"
        block
        class="mt-2 bg-[#004e89] font-semibold hover:bg-[#003d6b]"
      >
        Complete Signup
      </UButton>
    </form>
  </UCard>
</template>
