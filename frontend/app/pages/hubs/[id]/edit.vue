<script setup lang="ts">
import { useAuth } from '~/composables/useAuth';
import { useHubs } from '~/composables/useHubs';
import type { Hub } from '~/types/hub';
import type { HubFormPayload } from '~/components/hub/HubForm.vue';

definePageMeta({ middleware: 'auth', layout: 'page' });

const route = useRoute();
const { isAuthenticated } = useAuth();
const { fetchHub, updateHub, deleteHub } = useHubs();
const toast = useToast();

const hubId = computed(() => String(route.params.id));
const loading = ref(false);
const loadingHub = ref(true);
const isDeleteOpen = ref(false);
const deleteLoading = ref(false);

const hubData = ref<Hub | null>(null);
const hubFormRef = ref();

async function loadHub() {
  loadingHub.value = true;
  try {
    hubData.value = await fetchHub(hubId.value);
  } catch {
    toast.add({ title: 'Failed to load hub details.', color: 'error' });
    await navigateTo('/dashboard');
  } finally {
    loadingHub.value = false;
  }
}

async function handleSubmit(payload: HubFormPayload) {
  loading.value = true;
  try {
    await updateHub(hubId.value, {
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
      remove_gallery_image_ids: payload.removeGalleryImageIds,
      sports: payload.sports,
      contact_numbers: payload.contact_numbers,
      websites: payload.websites,
      is_active: payload.is_active,
      operating_hours: payload.operating_hours
    });
    toast.add({ title: 'Hub updated successfully!', color: 'success' });
    await navigateTo('/dashboard');
  } catch (e: unknown) {
    const err = e as {
      data?: { message?: string; errors?: Record<string, string[]> };
    };
    hubFormRef.value?.setErrors(err?.data?.errors ?? {});
    toast.add({
      title: err?.data?.message ?? 'Failed to update hub.',
      color: 'error'
    });
  } finally {
    loading.value = false;
  }
}

async function confirmDelete() {
  deleteLoading.value = true;
  try {
    await deleteHub(hubId.value);
    toast.add({ title: 'Hub deleted', color: 'success' });
    await navigateTo('/dashboard');
  } catch {
    toast.add({ title: 'Failed to delete hub', color: 'error' });
  } finally {
    deleteLoading.value = false;
  }
}

if (!isAuthenticated.value) {
  await navigateTo('/auth/login');
} else {
  await loadHub();
}
</script>

<template>
  <div>
    <NuxtLink
      to="/dashboard"
      class="mb-6 inline-flex items-center gap-1.5 text-sm font-medium text-[var(--aktiv-primary)] hover:underline"
    >
      <UIcon name="i-heroicons-arrow-left" class="h-4 w-4" />
      Back to Dashboard
    </NuxtLink>

    <div class="mx-auto max-w-2xl">
      <h1 class="mb-1 text-2xl font-bold text-[var(--aktiv-ink)]">Edit Hub</h1>
      <p class="mb-6 text-sm text-[var(--aktiv-muted)]">
        Update your sports hub details.
      </p>

      <UCard :ui="{ root: 'ring-1 ring-[var(--aktiv-border)] ' }">
        <div
          v-if="loadingHub"
          class="flex items-center gap-2 py-2 text-sm text-[var(--aktiv-muted)]"
        >
          <UIcon name="i-heroicons-arrow-path" class="h-4 w-4 animate-spin" />
          Loading hub details...
        </div>

        <HubForm
          v-else
          ref="hubFormRef"
          :loading="loading"
          :initial-data="hubData ?? undefined"
          :existing-cover-url="hubData?.cover_image_url ?? ''"
          :existing-gallery="hubData?.gallery_images ?? []"
          submit-label="Save Changes"
          @submit="handleSubmit"
        >
          <template #actions-left>
            <UButton
              type="button"
              icon="i-heroicons-trash"
              color="error"
              variant="ghost"
              @click="isDeleteOpen = true"
            >
              Delete Hub
            </UButton>
          </template>
        </HubForm>
      </UCard>
    </div>

    <!-- Delete Confirm Modal -->
    <UModal
      v-model:open="isDeleteOpen"
      title="Delete Hub"
      :ui="{ content: 'max-w-sm' }"
    >
      <template #body>
        <p class="text-sm text-[var(--aktiv-ink)]">
          Are you sure you want to delete
          <strong>{{ hubData?.name }}</strong
          >? This will permanently remove the hub and all its courts.
        </p>
        <div class="mt-5 flex justify-end gap-2">
          <UButton
            color="neutral"
            variant="ghost"
            @click="isDeleteOpen = false"
          >
            Cancel
          </UButton>
          <UButton
            color="error"
            :loading="deleteLoading"
            @click="confirmDelete"
          >
            Delete Hub
          </UButton>
        </div>
      </template>
    </UModal>
  </div>
</template>
