<script setup lang="ts">
import { useAuth } from '~/composables/useAuth';
import { useHubs } from '~/composables/useHubs';
import type { HubFormPayload } from '~/components/hub/HubForm.vue';

definePageMeta({ middleware: 'auth', layout: 'page' });

const { isAuthenticated } = useAuth();
const { createHub } = useHubs();
const toast = useToast();

const loading = ref(false);
const hubFormRef = ref();

async function handleSubmit(payload: HubFormPayload) {
  loading.value = true;
  try {
    await createHub({
      name: payload.name,
      description: payload.description,
      address: payload.address,
      address_line2: payload.address_line2,
      city: payload.city,
      zip_code: payload.zip_code,
      province: payload.province,
      country: payload.country,
      landmark: payload.landmark,
      lat: payload.lat,
      lng: payload.lng,
      cover_image: payload.coverImage,
      gallery_images: payload.galleryImages,
      sports: payload.sports,
      contact_numbers: payload.contact_numbers
    });
    toast.add({ title: 'Hub created successfully!', color: 'success' });
    await navigateTo('/dashboard');
  } catch (e: unknown) {
    const err = e as {
      data?: { message?: string; errors?: Record<string, string[]> };
    };
    hubFormRef.value?.setErrors(err?.data?.errors ?? {});
    toast.add({
      title: err?.data?.message ?? 'Failed to create hub.',
      color: 'error'
    });
  } finally {
    loading.value = false;
  }
}

if (!isAuthenticated.value) {
  await navigateTo('/auth/login');
}
</script>

<template>
  <div>
    <!-- Back link -->
    <NuxtLink
      to="/dashboard"
      class="mb-6 inline-flex items-center gap-1.5 text-sm font-medium text-[var(--aktiv-primary)] hover:underline"
    >
      <UIcon name="i-heroicons-arrow-left" class="h-4 w-4" />
      Back to Dashboard
    </NuxtLink>

    <div class="mx-auto max-w-2xl">
      <h1 class="mb-1 text-2xl font-bold text-[var(--aktiv-ink)]">
        Create a Hub
      </h1>
      <p class="mb-6 text-sm text-[var(--aktiv-muted)]">
        Fill in the details for your new sports hub.
      </p>

      <UCard :ui="{ root: 'ring-1 ring-[var(--aktiv-border)] shadow-sm' }">
        <HubForm
          ref="hubFormRef"
          :loading="loading"
          submit-label="Create Hub"
          @submit="handleSubmit"
        />
      </UCard>
    </div>
  </div>
</template>
