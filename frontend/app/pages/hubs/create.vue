<script setup lang="ts">
import { useAuth } from '~/composables/useAuth';
import { useHubs } from '~/composables/useHubs';
import type { HubFormPayload } from '~/components/hub/HubForm.vue';

const FORM_SECTIONS = [
  { id: 'section-basic', label: 'Basic Info' },
  { id: 'section-location', label: 'Location' },
  { id: 'section-hours', label: 'Operating Hours' },
  { id: 'section-media', label: 'Media' },
  { id: 'section-status', label: 'Visibility' }
];

definePageMeta({ middleware: 'auth', layout: 'dashboard-hub' });

const { isAuthenticated } = useAuth();
const { createHub } = useHubs();
const toast = useToast();

const loading = ref(false);
const hubFormRef = ref();
const formReady = ref(false);
onMounted(() => {
  setTimeout(() => {
    formReady.value = true;
  }, 100);
});

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
      contact_numbers: payload.contact_numbers,
      websites: payload.websites,
      is_active: payload.is_active,
      operating_hours: payload.operating_hours
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
    <div class="mx-auto w-full max-w-3xl px-4 py-8 md:px-6">
      <NuxtLink
        to="/dashboard/hubs"
        class="mb-6 inline-flex items-center gap-1.5 text-sm font-medium text-[var(--aktiv-primary)] hover:underline"
      >
        <UIcon name="i-heroicons-arrow-left" class="h-4 w-4" />
        Back to Hubs
      </NuxtLink>

      <h1 class="mb-1 text-2xl font-bold text-[var(--aktiv-ink)]">
        Create a Hub
      </h1>
      <p class="mb-6 text-sm text-[var(--aktiv-muted)]">
        Fill in the details for your new sports hub.
      </p>

      <UCard :ui="{ root: 'ring-1 ring-[var(--aktiv-border)] ' }">
        <div
          v-if="!formReady"
          class="flex items-center gap-2 py-2 text-sm text-[var(--aktiv-muted)]"
        >
          <UIcon name="i-heroicons-arrow-path" class="h-4 w-4 animate-spin" />
          Preparing form...
        </div>

        <HubForm
          v-else
          ref="hubFormRef"
          :loading="loading"
          submit-label="Create Hub"
          @submit="handleSubmit"
        />
      </UCard>
    </div>

    <HubFormNav :sections="FORM_SECTIONS" />
  </div>
</template>
