<script setup lang="ts">
import { z } from 'zod';
import type { HubContactNumber, HubWebsite } from '~/types/hub';
import {
  HUB_IMAGE_MAX_BYTES,
  HUB_IMAGE_MAX_SIZE_MB
} from '~/composables/useHubs';

export interface HubFormPayload {
  name: string;
  description?: string;
  address: string;
  address_line2?: string | null;
  city: string;
  zip_code: string;
  province: string;
  country: string;
  landmark?: string | null;
  lat: number;
  lng: number;
  sports: string[];
  contact_numbers: HubContactNumber[];
  websites: HubWebsite[];
  coverImage: File | null;
  galleryImages: File[];
  removeGalleryImageIds: number[];
  is_active: boolean;
}

interface GalleryImage {
  id: number;
  url: string;
  order: number;
}

interface InitialData {
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
  sports: string[];
  contact_numbers: HubContactNumber[];
  websites: HubWebsite[];
  is_active?: boolean;
}

const props = withDefaults(
  defineProps<{
    loading?: boolean;
    submitLabel?: string;
    initialData?: InitialData;
    existingCoverUrl?: string;
    existingGallery?: GalleryImage[];
  }>(),
  {
    loading: false,
    submitLabel: 'Submit',
    existingGallery: () => []
  }
);

const emit = defineEmits<{
  submit: [payload: HubFormPayload];
}>();

const toast = useToast();

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
  sports: [] as string[],
  contact_numbers: [] as HubContactNumber[],
  websites: [] as HubWebsite[],
  is_active: true
});

const coverImage = ref<File | null>(null);
const coverPreview = ref('');
const currentCoverUrl = ref(props.existingCoverUrl ?? '');
const removeGalleryImageIds = ref<number[]>([]);
const newGalleryImages = ref<File[]>([]);
const newGalleryPreviews = ref<string[]>([]);

watch(
  () => props.initialData,
  (data) => {
    if (!data) return;
    form.name = data.name;
    form.description = data.description ?? '';
    form.address = data.address;
    form.address_line2 = data.address_line2 ?? '';
    form.city = data.city;
    form.zip_code = data.zip_code ?? '';
    form.province = data.province ?? '';
    form.country = data.country ?? '';
    form.landmark = data.landmark ?? '';
    form.lat = data.lat ? parseFloat(data.lat) : null;
    form.lng = data.lng ? parseFloat(data.lng) : null;
    form.sports = [...data.sports];
    form.contact_numbers = data.contact_numbers.map((c) => ({ ...c }));
    form.websites = (data.websites ?? []).map((w) => ({ url: w.url }));
    form.is_active = data.is_active ?? true;
  },
  { immediate: true }
);

watch(
  () => props.existingCoverUrl,
  (url) => {
    currentCoverUrl.value = url ?? '';
  }
);

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
  sports: z.array(z.string()),
  is_active: z.boolean(),
  contact_numbers: z
    .array(
      z
        .object({
          type: z.enum(['mobile', 'landline']),
          number: z.string()
        })
        .superRefine((entry, ctx) => {
          if (entry.type === 'mobile' && !/^09\d{9}$/.test(entry.number)) {
            ctx.addIssue({
              code: z.ZodIssueCode.custom,
              message: 'Mobile number must be 11 digits starting with 09.',
              path: ['number']
            });
          }
          if (
            entry.type === 'landline' &&
            !/^0[2-9]\d{7,8}$/.test(entry.number)
          ) {
            ctx.addIssue({
              code: z.ZodIssueCode.custom,
              message: 'Landline must be 9–10 digits starting with 0.',
              path: ['number']
            });
          }
        })
    )
    .max(5)
    .optional(),
  websites: z
    .array(
      z.object({
        url: z
          .string()
          .url('Please enter a valid URL (e.g. https://example.com).')
      })
    )
    .max(5)
    .optional()
});

const fieldErrors = ref<Record<string, string[]>>({});

function setFieldErrorsFromZod(error: z.ZodError) {
  const nextErrors: Record<string, string[]> = {};
  for (const issue of error.issues) {
    const key = issue.path.join('.');
    if (!key) continue;
    if (!nextErrors[key]) nextErrors[key] = [];
    nextErrors[key].push(issue.message);
  }
  fieldErrors.value = nextErrors;
}

function fieldError(field: string) {
  return fieldErrors.value[field]?.[0];
}

function setErrors(errors: Record<string, string[]>) {
  fieldErrors.value = errors;
}

defineExpose({ setErrors });

function onPinUpdate(coords: { lat: number | null; lng: number | null }) {
  form.lat = coords.lat;
  form.lng = coords.lng;
}

function addContactNumber() {
  if (form.contact_numbers.length < 5) {
    form.contact_numbers.push({ type: 'mobile', number: '' });
  }
}

function removeContactNumber(index: number) {
  form.contact_numbers.splice(index, 1);
}

function contactNumberMaxLength(type: 'mobile' | 'landline') {
  return type === 'mobile' ? 11 : 10;
}

function contactNumberError(index: number) {
  return (
    fieldErrors.value[`contact_numbers.${index}.number`]?.[0] ??
    fieldErrors.value[`contact_numbers.${index}.type`]?.[0]
  );
}

function addWebsite() {
  if (form.websites.length < 5) {
    form.websites.push({ url: '' });
  }
}

function removeWebsite(index: number) {
  form.websites.splice(index, 1);
}

function websiteError(index: number) {
  return fieldErrors.value[`websites.${index}.url`]?.[0];
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

  const remainingSlots =
    10 -
    (props.existingGallery.length - removeGalleryImageIds.value.length) -
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

function handleSubmit() {
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
    sports: form.sports,
    is_active: form.is_active,
    contact_numbers: form.contact_numbers,
    websites: form.websites
  });

  if (!parsed.success) {
    setFieldErrorsFromZod(parsed.error);
    toast.add({
      title: 'Please fix the highlighted fields before saving.',
      color: 'error'
    });
    return;
  }

  emit('submit', {
    name: parsed.data.name,
    description: parsed.data.description,
    address: parsed.data.address,
    address_line2: parsed.data.address_line2 ?? null,
    city: parsed.data.city,
    zip_code: parsed.data.zip_code,
    province: parsed.data.province,
    country: parsed.data.country,
    landmark: parsed.data.landmark ?? null,
    lat: parsed.data.lat as number,
    lng: parsed.data.lng as number,
    sports: parsed.data.sports,
    contact_numbers: parsed.data.contact_numbers ?? [],
    websites: parsed.data.websites ?? [],
    coverImage: coverImage.value,
    galleryImages: newGalleryImages.value,
    removeGalleryImageIds: removeGalleryImageIds.value,
    is_active: parsed.data.is_active
  });
}

onUnmounted(() => {
  if (coverPreview.value) {
    URL.revokeObjectURL(coverPreview.value);
  }
  newGalleryPreviews.value.forEach((preview) => URL.revokeObjectURL(preview));
});
</script>

<template>
  <form class="space-y-5" @submit.prevent="handleSubmit">
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

    <!-- Contact Numbers -->
    <div class="space-y-2">
      <p class="text-sm font-medium text-[var(--aktiv-ink)]">
        Contact Numbers
        <span class="ml-1 text-xs font-normal text-[var(--aktiv-muted)]"
          >(optional, up to 5)</span
        >
      </p>

      <div
        v-for="(entry, index) in form.contact_numbers"
        :key="index"
        class="flex items-start gap-2"
      >
        <USelect
          v-model="entry.type"
          :items="[
            { label: 'Mobile', value: 'mobile' },
            { label: 'Landline', value: 'landline' }
          ]"
          class="w-32 shrink-0"
        />
        <div class="min-w-0 flex-1">
          <UInput
            v-model="entry.number"
            :placeholder="
              entry.type === 'mobile' ? '09XXXXXXXXX' : '02XXXXXXXX'
            "
            :maxlength="contactNumberMaxLength(entry.type)"
            class="w-full"
            :ui="{
              base: contactNumberError(index)
                ? 'ring-1 ring-[var(--aktiv-danger-fg)]'
                : ''
            }"
          />
          <p
            v-if="contactNumberError(index)"
            class="mt-0.5 text-xs text-[var(--aktiv-danger-fg)]"
          >
            {{ contactNumberError(index) }}
          </p>
        </div>
        <button
          type="button"
          class="mt-1.5 shrink-0 text-[var(--aktiv-muted)] hover:text-[var(--aktiv-danger-fg)]"
          aria-label="Remove contact number"
          @click="removeContactNumber(index)"
        >
          <UIcon name="i-heroicons-x-mark" class="h-4 w-4" />
        </button>
      </div>

      <UButton
        v-if="form.contact_numbers.length < 5"
        type="button"
        variant="ghost"
        color="neutral"
        size="xs"
        icon="i-heroicons-plus"
        @click="addContactNumber"
      >
        Add
      </UButton>
    </div>

    <!-- Websites -->
    <div class="space-y-2">
      <p class="text-sm font-medium text-[var(--aktiv-ink)]">
        Websites
        <span class="ml-1 text-xs font-normal text-[var(--aktiv-muted)]"
          >(optional, up to 5)</span
        >
      </p>

      <div
        v-for="(entry, index) in form.websites"
        :key="index"
        class="flex items-start gap-2"
      >
        <div class="min-w-0 flex-1">
          <UInput
            v-model="entry.url"
            placeholder="https://example.com"
            class="w-full"
            :ui="{
              base: websiteError(index)
                ? 'ring-1 ring-[var(--aktiv-danger-fg)]'
                : ''
            }"
          />
          <p
            v-if="websiteError(index)"
            class="mt-0.5 text-xs text-[var(--aktiv-danger-fg)]"
          >
            {{ websiteError(index) }}
          </p>
        </div>
        <button
          type="button"
          class="mt-1.5 shrink-0 text-[var(--aktiv-muted)] hover:text-[var(--aktiv-danger-fg)]"
          aria-label="Remove website"
          @click="removeWebsite(index)"
        >
          <UIcon name="i-heroicons-x-mark" class="h-4 w-4" />
        </button>
      </div>

      <UButton
        v-if="form.websites.length < 5"
        type="button"
        variant="ghost"
        color="neutral"
        size="xs"
        icon="i-heroicons-plus"
        @click="addWebsite"
      >
        Add another
      </UButton>
    </div>

    <!-- Location -->
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

      <UFormField label="Address 1" required :error="fieldError('address')">
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
          placeholder="Unit, floor, building, suite…"
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
        <UFormField label="Zip Code" required :error="fieldError('zip_code')">
          <UInput
            v-model="form.zip_code"
            placeholder="e.g. 1108"
            required
            class="w-full"
          />
        </UFormField>
      </div>

      <UFormField label="Province" required :error="fieldError('province')">
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

      <UFormField label="Landmark (optional)" :error="fieldError('landmark')">
        <UInput
          v-model="form.landmark"
          placeholder="e.g. near Petron station, behind SM Mall"
          class="w-full"
        />
      </UFormField>
    </div>

    <!-- Cover Image -->
    <UFormField
      label="Cover Image (optional)"
      :error="fieldError('cover_image')"
    >
      <img
        v-if="coverPreview || currentCoverUrl"
        :src="coverPreview || currentCoverUrl"
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

    <!-- Gallery Images -->
    <UFormField label="Gallery Images" :error="fieldError('gallery_images')">
      <p class="text-xs text-[var(--aktiv-muted)]">
        Up to 10 images total, max {{ HUB_IMAGE_MAX_SIZE_MB }}MB each.
        <template v-if="existingGallery.length">
          Mark existing images for removal or add new ones.
        </template>
      </p>

      <div
        v-if="existingGallery.length"
        class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3"
      >
        <div v-for="image in existingGallery" :key="image.id" class="relative">
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
            {{ removeGalleryImageIds.includes(image.id) ? 'Undo' : 'Remove' }}
          </button>
        </div>
      </div>

      <input
        type="file"
        multiple
        accept="image/*"
        class="mt-3 block w-full text-sm text-[var(--aktiv-muted)] file:mr-4 file:rounded-md file:border-0 file:bg-[var(--aktiv-primary)] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-[var(--aktiv-primary-hover)]"
        @change="onGalleryImagesChange"
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

    <!-- Sports -->
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
                ? (form.sports = form.sports.filter((s) => s !== opt.value))
                : form.sports.push(opt.value)
            "
          />
          {{ opt.label }}
        </label>
      </div>
      <p class="mt-1.5 text-xs text-[var(--aktiv-muted)]">
        You can also manage sports by adding courts with specific sports later.
      </p>
    </UFormField>

    <!-- Visibility -->
    <div class="space-y-1.5">
      <div class="flex items-center gap-3">
        <USwitch v-model="form.is_active" />
        <p class="text-sm font-medium text-[var(--aktiv-ink)]">
          {{ form.is_active ? 'Active' : 'Inactive' }}
        </p>
      </div>
      <p class="text-xs text-[var(--aktiv-muted)]">
        When active, this hub will appear in the public hub list. Deactivate it
        to hide it from visitors while you set things up.
      </p>
    </div>

    <!-- Footer -->
    <div
      class="flex items-center justify-between gap-3 border-t border-[var(--aktiv-border)] pt-4"
    >
      <div>
        <slot name="actions-left" />
      </div>
      <div class="flex gap-3">
        <UButton to="/dashboard" color="neutral" variant="ghost">
          Cancel
        </UButton>
        <UButton
          type="submit"
          :loading="loading"
          class="bg-[var(--aktiv-primary)] font-semibold hover:bg-[var(--aktiv-primary-hover)]"
        >
          {{ submitLabel }}
        </UButton>
      </div>
    </div>
  </form>
</template>
