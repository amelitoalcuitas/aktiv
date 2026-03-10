<script setup lang="ts">
import { useAuth } from '~/composables/useAuth';
import {
  HUB_IMAGE_MAX_BYTES,
  HUB_IMAGE_MAX_SIZE_MB,
  useHubs
} from '~/composables/useHubs';

definePageMeta({ middleware: 'auth', layout: 'page' });

const route = useRoute();
const { isAuthenticated } = useAuth();
const { fetchHub, updateHub } = useHubs();
const toast = useToast();

const hubId = computed(() => String(route.params.id));
const loading = ref(false);
const loadingHub = ref(true);

const form = reactive({
  name: '',
  description: '',
  address: '',
  address_line2: '',
  city: '',
  zip_code: '',
  province: '',
  country: '',
  landmark: '',
  lat: null as number | null,
  lng: null as number | null,
  sports: [] as string[]
});

const coverImage = ref<File | null>(null);
const coverPreview = ref('');
const existingCoverUrl = ref('');
const existingGallery = ref<Array<{ id: number; url: string; order: number }>>(
  []
);
const removeGalleryImageIds = ref<number[]>([]);
const newGalleryImages = ref<File[]>([]);
const newGalleryPreviews = ref<string[]>([]);

const SPORT_OPTIONS = [
  { label: 'Pickleball', value: 'pickleball' },
  { label: 'Badminton', value: 'badminton' },
  { label: 'Basketball', value: 'basketball' },
  { label: 'Tennis', value: 'tennis' },
  { label: 'Volleyball', value: 'volleyball' }
];

const fieldErrors = ref<Record<string, string[]>>({});

function onPinUpdate(coords: { lat: number | null; lng: number | null }) {
  form.lat = coords.lat;
  form.lng = coords.lng;
}

function fieldError(field: string) {
  return fieldErrors.value[field]?.[0];
}

function fillFormFromHub(hub: {
  name: string;
  description: string | null;
  address: string;
  address_line2: string | null;
  city: string;
  zip_code: string | null;
  province: string | null;
  country: string | null;
  landmark: string | null;
  lat: string | null;
  lng: string | null;
  cover_image_url: string | null;
  gallery_images: Array<{ id: number; url: string; order: number }>;
  sports: string[];
}) {
  form.name = hub.name;
  form.description = hub.description ?? '';
  form.address = hub.address;
  form.address_line2 = hub.address_line2 ?? '';
  form.city = hub.city;
  form.zip_code = hub.zip_code ?? '';
  form.province = hub.province ?? '';
  form.country = hub.country ?? '';
  form.landmark = hub.landmark ?? '';
  form.lat = hub.lat ? parseFloat(hub.lat) : null;
  form.lng = hub.lng ? parseFloat(hub.lng) : null;
  existingCoverUrl.value = hub.cover_image_url ?? '';
  existingGallery.value = [...hub.gallery_images];
  removeGalleryImageIds.value = [];
  form.sports = [...hub.sports];
}

function onCoverImageChange(event: Event) {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0] ?? null;

  if (file && file.size > HUB_IMAGE_MAX_BYTES) {
    toast.add({
      title: `Cover image must be ${HUB_IMAGE_MAX_SIZE_MB}MB or smaller.`,
      color: 'error'
    });
    input.value = '';
    return;
  }

  coverImage.value = file;
  if (coverPreview.value) {
    URL.revokeObjectURL(coverPreview.value);
    coverPreview.value = '';
  }

  if (file) {
    coverPreview.value = URL.createObjectURL(file);
  }
}

function onNewGalleryImagesChange(event: Event) {
  const input = event.target as HTMLInputElement;
  const files = Array.from(input.files ?? []);
  if (!files.length) return;

  const validFiles = files.filter((file) => file.size <= HUB_IMAGE_MAX_BYTES);
  const oversizedCount = files.length - validFiles.length;
  if (oversizedCount > 0) {
    toast.add({
      title: `${oversizedCount} image(s) skipped. Max ${HUB_IMAGE_MAX_SIZE_MB}MB per image.`,
      color: 'error'
    });
  }

  const remainingSlots =
    10 -
    (existingGallery.value.length - removeGalleryImageIds.value.length) -
    newGalleryImages.value.length;

  const filesToAdd = validFiles.slice(0, Math.max(0, remainingSlots));

  filesToAdd.forEach((file) => {
    newGalleryImages.value.push(file);
    newGalleryPreviews.value.push(URL.createObjectURL(file));
  });

  input.value = '';
}

function removeNewGalleryImage(index: number) {
  const [preview] = newGalleryPreviews.value.splice(index, 1);
  newGalleryImages.value.splice(index, 1);
  if (preview) {
    URL.revokeObjectURL(preview);
  }
}

function toggleExistingGalleryRemoval(imageId: number) {
  if (removeGalleryImageIds.value.includes(imageId)) {
    removeGalleryImageIds.value = removeGalleryImageIds.value.filter(
      (id) => id !== imageId
    );
    return;
  }

  removeGalleryImageIds.value.push(imageId);
}

async function loadHub() {
  loadingHub.value = true;
  try {
    const hub = await fetchHub(hubId.value);
    fillFormFromHub(hub);
  } catch {
    toast.add({ title: 'Failed to load hub details.', color: 'error' });
    await navigateTo('/dashboard');
  } finally {
    loadingHub.value = false;
  }
}

async function handleSubmit() {
  loading.value = true;
  fieldErrors.value = {};
  try {
    await updateHub(hubId.value, {
      name: form.name,
      description: form.description || undefined,
      address: form.address,
      address_line2: form.address_line2 || null,
      city: form.city,
      zip_code: form.zip_code,
      province: form.province,
      country: form.country,
      landmark: form.landmark || null,
      lat: form.lat,
      lng: form.lng,
      cover_image: coverImage.value,
      gallery_images: newGalleryImages.value,
      remove_gallery_image_ids: removeGalleryImageIds.value,
      sports: form.sports
    });
    toast.add({ title: 'Hub updated successfully!', color: 'success' });
    await navigateTo('/dashboard');
  } catch (e: unknown) {
    const err = e as {
      data?: { message?: string; errors?: Record<string, string[]> };
    };
    fieldErrors.value = err?.data?.errors ?? {};
    toast.add({
      title: err?.data?.message ?? 'Failed to update hub.',
      color: 'error'
    });
  } finally {
    loading.value = false;
  }
}

if (!isAuthenticated.value) {
  await navigateTo('/auth/login');
} else {
  await loadHub();
}

onUnmounted(() => {
  if (coverPreview.value) {
    URL.revokeObjectURL(coverPreview.value);
  }

  newGalleryPreviews.value.forEach((preview) => URL.revokeObjectURL(preview));
});
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

      <UCard :ui="{ root: 'ring-1 ring-[var(--aktiv-border)] shadow-sm' }">
        <div
          v-if="loadingHub"
          class="flex items-center gap-2 py-2 text-sm text-[var(--aktiv-muted)]"
        >
          <UIcon name="i-heroicons-arrow-path" class="h-4 w-4 animate-spin" />
          Loading hub details...
        </div>

        <form v-else class="space-y-5" @submit.prevent="handleSubmit">
          <UFormField label="Hub Name" required :error="fieldError('name')">
            <UInput
              v-model="form.name"
              placeholder="e.g. Sunnyvale Tennis Club"
              required
              class="w-full"
            />
          </UFormField>

          <UFormField label="Description" :error="fieldError('description')">
            <UTextarea
              v-model="form.description"
              placeholder="Brief description of your hub (optional)"
              :rows="3"
              class="w-full"
            />
          </UFormField>

          <div class="space-y-3">
            <p class="text-sm font-medium text-[var(--aktiv-ink)]">
              Location <span class="text-[var(--aktiv-danger-fg)]">*</span>
            </p>

            <ClientOnly>
              <HubLocationPicker
                :model-value="{ lat: form.lat, lng: form.lng }"
                @update:model-value="onPinUpdate"
              />
              <template #fallback>
                <div
                  class="flex h-72 w-full items-center justify-center rounded-xl border border-[var(--aktiv-border)] bg-[var(--aktiv-background)] text-sm text-[var(--aktiv-muted)]"
                >
                  Loading map...
                </div>
              </template>
            </ClientOnly>

            <p
              v-if="fieldError('lat') || fieldError('lng')"
              class="text-xs text-[var(--aktiv-danger-fg)]"
            >
              {{ fieldError('lat') ?? fieldError('lng') }}
            </p>

            <UFormField
              label="Address 1"
              required
              :error="fieldError('address')"
            >
              <UInput
                v-model="form.address"
                placeholder="e.g. 45 Katipunan Ave, Loyola Heights"
                required
                class="w-full"
              />
            </UFormField>

            <UFormField
              label="Address 2 (optional)"
              :error="fieldError('address_line2')"
            >
              <UInput
                v-model="form.address_line2"
                placeholder="Unit, floor, building, suite..."
                class="w-full"
              />
            </UFormField>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <UFormField label="City" required :error="fieldError('city')">
                <UInput
                  v-model="form.city"
                  placeholder="e.g. Quezon City"
                  required
                  class="w-full"
                />
              </UFormField>
              <UFormField
                label="Zip Code"
                required
                :error="fieldError('zip_code')"
              >
                <UInput
                  v-model="form.zip_code"
                  placeholder="e.g. 1108"
                  required
                  class="w-full"
                />
              </UFormField>
            </div>

            <UFormField
              label="Province"
              required
              :error="fieldError('province')"
            >
              <UInput
                v-model="form.province"
                placeholder="e.g. Metro Manila"
                required
                class="w-full"
              />
            </UFormField>

            <UFormField label="Country" required :error="fieldError('country')">
              <UInput
                v-model="form.country"
                placeholder="e.g. Philippines"
                required
                class="w-full"
              />
            </UFormField>

            <UFormField
              label="Landmark (optional)"
              :error="fieldError('landmark')"
            >
              <UInput
                v-model="form.landmark"
                placeholder="e.g. near Petron station, behind SM Mall"
                class="w-full"
              />
            </UFormField>
          </div>

          <UFormField
            label="Cover Image (optional)"
            :error="fieldError('cover_image')"
          >
            <img
              v-if="coverPreview || existingCoverUrl"
              :src="coverPreview || existingCoverUrl"
              alt="Cover preview"
              class="mb-3 h-40 w-full rounded-lg border border-[var(--aktiv-border)] object-cover"
            />
            <input
              type="file"
              accept="image/*"
              class="block w-full text-sm text-[var(--aktiv-muted)] file:mr-4 file:rounded-md file:border-0 file:bg-[var(--aktiv-primary)] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-[var(--aktiv-primary-hover)]"
              @change="onCoverImageChange"
            />
          </UFormField>

          <UFormField
            label="Gallery Images"
            :error="fieldError('gallery_images')"
          >
            <p class="text-xs text-[var(--aktiv-muted)]">
              Up to 10 images total, max {{ HUB_IMAGE_MAX_SIZE_MB }}MB each.
              Mark existing images for removal or add new ones.
            </p>

            <div
              v-if="existingGallery.length"
              class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3"
            >
              <div
                v-for="image in existingGallery"
                :key="image.id"
                class="relative"
              >
                <img
                  :src="image.url"
                  :alt="`Gallery image ${image.id}`"
                  class="h-28 w-full rounded-lg border border-[var(--aktiv-border)] object-cover"
                  :class="
                    removeGalleryImageIds.includes(image.id) ? 'opacity-40' : ''
                  "
                />
                <button
                  type="button"
                  class="absolute right-1 top-1 rounded px-2 py-0.5 text-xs text-white"
                  :class="
                    removeGalleryImageIds.includes(image.id)
                      ? 'bg-emerald-600'
                      : 'bg-black/70'
                  "
                  @click="toggleExistingGalleryRemoval(image.id)"
                >
                  {{
                    removeGalleryImageIds.includes(image.id) ? 'Undo' : 'Remove'
                  }}
                </button>
              </div>
            </div>

            <input
              type="file"
              multiple
              accept="image/*"
              class="mt-3 block w-full text-sm text-[var(--aktiv-muted)] file:mr-4 file:rounded-md file:border-0 file:bg-[var(--aktiv-primary)] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-[var(--aktiv-primary-hover)]"
              @change="onNewGalleryImagesChange"
            />

            <div
              v-if="newGalleryPreviews.length"
              class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3"
            >
              <div
                v-for="(preview, index) in newGalleryPreviews"
                :key="`${preview}-${index}`"
                class="relative"
              >
                <img
                  :src="preview"
                  :alt="`New gallery image ${index + 1}`"
                  class="h-28 w-full rounded-lg border border-[var(--aktiv-border)] object-cover"
                />
                <button
                  type="button"
                  class="absolute right-1 top-1 rounded bg-black/70 px-2 py-0.5 text-xs text-white"
                  @click="removeNewGalleryImage(index)"
                >
                  Remove
                </button>
              </div>
            </div>
          </UFormField>

          <UFormField label="Sports">
            <div class="flex flex-wrap gap-2 pt-1">
              <label
                v-for="opt in SPORT_OPTIONS"
                :key="opt.value"
                class="flex cursor-pointer items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium transition"
                :class="
                  form.sports.includes(opt.value)
                    ? 'border-[var(--aktiv-primary)] bg-[#e8f0f8] text-[var(--aktiv-primary)]'
                    : 'border-[var(--aktiv-border)] text-[var(--aktiv-muted)] hover:border-[var(--aktiv-primary)]'
                "
              >
                <input
                  type="checkbox"
                  class="sr-only"
                  :value="opt.value"
                  :checked="form.sports.includes(opt.value)"
                  @change="
                    form.sports.includes(opt.value)
                      ? (form.sports = form.sports.filter(
                          (s) => s !== opt.value
                        ))
                      : form.sports.push(opt.value)
                  "
                />
                {{ opt.label }}
              </label>
            </div>
            <p class="mt-1.5 text-xs text-[var(--aktiv-muted)]">
              You can also manage sports by adding courts with specific sports
              later.
            </p>
          </UFormField>

          <div
            class="flex justify-end gap-3 border-t border-[var(--aktiv-border)] pt-4"
          >
            <UButton to="/dashboard" color="neutral" variant="ghost">
              Cancel
            </UButton>
            <UButton
              type="submit"
              :loading="loading"
              class="bg-[var(--aktiv-primary)] font-semibold hover:bg-[var(--aktiv-primary-hover)]"
            >
              Save Changes
            </UButton>
          </div>
        </form>
      </UCard>
    </div>
  </div>
</template>
