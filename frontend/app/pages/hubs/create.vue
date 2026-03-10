<script setup lang="ts">
import { z } from 'zod';
import { useAuth } from '~/composables/useAuth';
import {
  HUB_IMAGE_MAX_BYTES,
  HUB_IMAGE_MAX_SIZE_MB,
  useHubs
} from '~/composables/useHubs';

definePageMeta({ middleware: 'auth', layout: 'page' });

const { isAuthenticated } = useAuth();
const { createHub } = useHubs();
const toast = useToast();

const loading = ref(false);
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
const galleryImages = ref<File[]>([]);
const galleryPreviews = ref<string[]>([]);

const SPORT_OPTIONS = [
  { label: 'Pickleball', value: 'pickleball' },
  { label: 'Badminton', value: 'badminton' },
  { label: 'Basketball', value: 'basketball' },
  { label: 'Tennis', value: 'tennis' },
  { label: 'Volleyball', value: 'volleyball' }
];

const optionalTrimmedStringSchema = z.preprocess((value) => {
  if (value === null || value === undefined) return undefined;
  const normalized = String(value).trim();
  return normalized.length ? normalized : undefined;
}, z.string().optional());

const hubFormSchema = z.object({
  name: z.string().trim().min(1, 'Hub name is required.'),
  description: optionalTrimmedStringSchema,
  address: z.string().trim().min(1, 'Address 1 is required.'),
  address_line2: optionalTrimmedStringSchema,
  city: z.string().trim().min(1, 'City is required.'),
  zip_code: z.string().trim().min(1, 'Zip Code is required.'),
  province: z.string().trim().min(1, 'Province is required.'),
  country: z.string().trim().min(1, 'Country is required.'),
  landmark: optionalTrimmedStringSchema,
  lat: z
    .number()
    .nullable()
    .refine(
      (value) => value !== null,
      'Please pin your hub location on the map.'
    ),
  lng: z
    .number()
    .nullable()
    .refine(
      (value) => value !== null,
      'Please pin your hub location on the map.'
    ),
  sports: z.array(z.string())
});

const fieldErrors = ref<Record<string, string[]>>({});

function setFieldErrorsFromZod(error: z.ZodError) {
  const nextErrors: Record<string, string[]> = {};

  for (const issue of error.issues) {
    const key = issue.path[0];
    if (typeof key !== 'string') continue;
    if (!nextErrors[key]) nextErrors[key] = [];
    nextErrors[key].push(issue.message);
  }

  fieldErrors.value = nextErrors;
}

function onPinUpdate(coords: { lat: number | null; lng: number | null }) {
  form.lat = coords.lat;
  form.lng = coords.lng;
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

function onGalleryImagesChange(event: Event) {
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

  const availableSlots = 10 - galleryImages.value.length;
  const filesToAdd = validFiles.slice(0, Math.max(0, availableSlots));

  filesToAdd.forEach((file) => {
    galleryImages.value.push(file);
    galleryPreviews.value.push(URL.createObjectURL(file));
  });

  input.value = '';
}

function removeGalleryImage(index: number) {
  const [preview] = galleryPreviews.value.splice(index, 1);
  galleryImages.value.splice(index, 1);
  if (preview) {
    URL.revokeObjectURL(preview);
  }
}

async function handleSubmit() {
  fieldErrors.value = {};

  const parsed = hubFormSchema.safeParse({
    name: form.name,
    description: form.description,
    address: form.address,
    address_line2: form.address_line2,
    city: form.city,
    zip_code: form.zip_code,
    province: form.province,
    country: form.country,
    landmark: form.landmark,
    lat: form.lat,
    lng: form.lng,
    sports: form.sports
  });

  if (!parsed.success) {
    setFieldErrorsFromZod(parsed.error);
    toast.add({
      title: 'Please fix the highlighted fields before saving.',
      color: 'error'
    });
    return;
  }

  loading.value = true;
  try {
    await createHub({
      name: parsed.data.name,
      description: parsed.data.description,
      address: parsed.data.address,
      address_line2: parsed.data.address_line2 ?? null,
      city: parsed.data.city,
      zip_code: parsed.data.zip_code,
      province: parsed.data.province,
      country: parsed.data.country,
      landmark: parsed.data.landmark ?? null,
      lat: parsed.data.lat,
      lng: parsed.data.lng,
      cover_image: coverImage.value,
      gallery_images: galleryImages.value,
      sports: parsed.data.sports
    });
    toast.add({ title: 'Hub created successfully!', color: 'success' });
    await navigateTo('/dashboard');
  } catch (e: unknown) {
    const err = e as {
      data?: { message?: string; errors?: Record<string, string[]> };
    };
    fieldErrors.value = err?.data?.errors ?? {};
    toast.add({
      title: err?.data?.message ?? 'Failed to create hub.',
      color: 'error'
    });
  } finally {
    loading.value = false;
  }
}

function fieldError(field: string) {
  return fieldErrors.value[field]?.[0];
}

if (!isAuthenticated.value) {
  await navigateTo('/auth/login');
}

onUnmounted(() => {
  if (coverPreview.value) {
    URL.revokeObjectURL(coverPreview.value);
  }

  galleryPreviews.value.forEach((preview) => URL.revokeObjectURL(preview));
});
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
        <form class="space-y-5" @submit.prevent="handleSubmit">
          <!-- Name -->
          <UFormField label="Hub Name" required :error="fieldError('name')">
            <UInput
              v-model="form.name"
              placeholder="e.g. Sunnyvale Tennis Club"
              required
              class="w-full"
            />
          </UFormField>

          <!-- Description -->
          <UFormField label="Description" :error="fieldError('description')">
            <UTextarea
              v-model="form.description"
              placeholder="Brief description of your hub (optional)"
              :rows="3"
              class="w-full"
            />
          </UFormField>

          <!-- Location — map pin -->
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
                  Loading map…
                </div>
              </template>
            </ClientOnly>

            <p
              v-if="fieldError('lat') || fieldError('lng')"
              class="text-xs text-[var(--aktiv-danger-fg)]"
            >
              {{ fieldError('lat') ?? fieldError('lng') }}
            </p>

            <!-- Address fields -->

            <!-- Address 1 -->
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

            <!-- Address 2 -->
            <UFormField
              label="Address 2 (optional)"
              :error="fieldError('address_line2')"
            >
              <UInput
                v-model="form.address_line2"
                placeholder="Unit, floor, building, suite…"
                class="w-full"
              />
            </UFormField>

            <!-- City + Zip Code -->
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

            <!-- Province -->
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

            <!-- Country -->
            <UFormField label="Country" required :error="fieldError('country')">
              <UInput
                v-model="form.country"
                placeholder="e.g. Philippines"
                required
                class="w-full"
              />
            </UFormField>

            <!-- Landmark -->
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
            <input
              type="file"
              accept="image/*"
              class="block w-full text-sm text-[var(--aktiv-muted)] file:mr-4 file:rounded-md file:border-0 file:bg-[var(--aktiv-primary)] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-[var(--aktiv-primary-hover)]"
              @change="onCoverImageChange"
            />
            <img
              v-if="coverPreview"
              :src="coverPreview"
              alt="Cover preview"
              class="mt-3 h-40 w-full rounded-lg border border-[var(--aktiv-border)] object-cover"
            />
          </UFormField>

          <UFormField
            label="Gallery Images (optional)"
            :error="fieldError('gallery_images')"
          >
            <input
              type="file"
              multiple
              accept="image/*"
              class="block w-full text-sm text-[var(--aktiv-muted)] file:mr-4 file:rounded-md file:border-0 file:bg-[var(--aktiv-primary)] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-[var(--aktiv-primary-hover)]"
              @change="onGalleryImagesChange"
            />

            <p class="mt-2 text-xs text-[var(--aktiv-muted)]">
              Up to 10 images, max {{ HUB_IMAGE_MAX_SIZE_MB }}MB each. Files are
              resized and compressed on upload.
            </p>

            <div
              v-if="galleryPreviews.length"
              class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3"
            >
              <div
                v-for="(preview, index) in galleryPreviews"
                :key="`${preview}-${index}`"
                class="relative"
              >
                <img
                  :src="preview"
                  :alt="`Gallery preview ${index + 1}`"
                  class="h-28 w-full rounded-lg border border-[var(--aktiv-border)] object-cover"
                />
                <button
                  type="button"
                  class="absolute right-1 top-1 rounded bg-black/70 px-2 py-0.5 text-xs text-white"
                  @click="removeGalleryImage(index)"
                >
                  Remove
                </button>
              </div>
            </div>
          </UFormField>

          <!-- Sports multi-select -->
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

          <!-- Submit -->
          <div
            class="flex justify-end gap-3 border-t border-[var(--aktiv-border)] pt-4"
          >
            <UButton to="/dashboard" color="neutral" variant="ghost"
              >Cancel</UButton
            >
            <UButton
              type="submit"
              :loading="loading"
              class="bg-[var(--aktiv-primary)] font-semibold hover:bg-[var(--aktiv-primary-hover)]"
            >
              Create Hub
            </UButton>
          </div>
        </form>
      </UCard>
    </div>
  </div>
</template>
