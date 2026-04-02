<script setup lang="ts">
import { z } from 'zod';
import type {
  HubContactNumber,
  HubWebsite,
  OperatingHoursEntry
} from '~/types/hub';
import {
  HUB_IMAGE_MAX_BYTES,
  HUB_IMAGE_MAX_SIZE_MB
} from '~/composables/useHubs';
import { LINK_PLATFORMS } from '~/types/links';

export interface HubFormPayload {
  name: string;
  username: string;
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
  contact_numbers: HubContactNumber[];
  websites: HubWebsite[];
  coverImage: File | null;
  galleryImages: File[];
  removeGalleryImageIds: number[];
  is_active: boolean;
  operating_hours: OperatingHoursEntry[];
}

interface GalleryImage {
  id: number;
  url: string;
  order: number;
}

interface InitialData {
  name: string;
  username?: string | null;
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
  contact_numbers: HubContactNumber[];
  websites: HubWebsite[];
  is_active?: boolean;
  operating_hours?: OperatingHoursEntry[];
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
const { checkHubUsernameAvailability } = useHubs();

const form = reactive({
  name: '',
  username: '',
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
  contact_numbers: [] as HubContactNumber[],
  websites: [] as HubWebsite[],
  is_active: true
});

const usernameStatus = ref<'idle' | 'checking' | 'verified' | 'taken' | 'invalid'>('idle');
const usernameMessage = ref('');
const verifiedUsername = ref('');
const usernameTouched = ref(false);

const DAY_NAMES = [
  'Sunday',
  'Monday',
  'Tuesday',
  'Wednesday',
  'Thursday',
  'Friday',
  'Saturday'
];

const CONTACT_TYPE_OPTIONS = [
  {
    value: 'mobile' as const,
    label: 'Mobile',
    icon: 'i-heroicons-device-phone-mobile'
  },
  {
    value: 'landline' as const,
    label: 'Landline',
    icon: 'i-heroicons-phone'
  }
];

function defaultOperatingHours(): OperatingHoursEntry[] {
  return Array.from({ length: 7 }, (_, i) => ({
    day_of_week: i,
    opens_at: '06:00',
    closes_at: '23:00',
    is_closed: false
  }));
}

const operatingHours = ref<OperatingHoursEntry[]>(defaultOperatingHours());

const HOUR_OPTIONS = Array.from({ length: 24 }, (_, h) => {
  const label =
    h === 0
      ? '12 AM'
      : h < 12
        ? `${h} AM`
        : h === 12
          ? '12 PM'
          : `${h - 12} PM`;
  const value = `${String(h).padStart(2, '0')}:00`;
  return { label, value };
});

const coverImage = ref<File | null>(null);
const coverPreview = ref('');
const currentCoverUrl = ref(props.existingCoverUrl ?? '');
const removeGalleryImageIds = ref<number[]>([]);
const newGalleryImages = ref<File[]>([]);

const activeExistingGallery = computed(() =>
  props.existingGallery.filter((img) => !removeGalleryImageIds.value.includes(img.id))
);

const activeExistingGalleryUrls = computed(() =>
  activeExistingGallery.value.map((img) => img.url)
);

function removeExistingGallery(index: number) {
  const img = activeExistingGallery.value[index];
  if (img) removeGalleryImageIds.value.push(img.id);
}

watch(
  () => props.initialData,
  (data) => {
    if (!data) return;
    form.name = data.name;
    form.username = data.username ?? '';
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
    form.contact_numbers = data.contact_numbers.map((c) => ({ ...c }));
    form.websites = (data.websites ?? []).map((w) => ({
      platform: w.platform ?? 'other',
      url: w.url
    }));
    form.is_active = data.is_active ?? true;
    verifiedUsername.value = data.username ?? '';
    usernameTouched.value = !!data.username;
    usernameStatus.value = data.username ? 'verified' : 'idle';
    usernameMessage.value = data.username ? 'Username verified.' : '';
    if (data.operating_hours && data.operating_hours.length > 0) {
      const base = defaultOperatingHours();
      for (const oh of data.operating_hours) {
        const entry = base[oh.day_of_week];
        if (entry) {
          entry.opens_at = oh.opens_at?.slice(0, 5) ?? oh.opens_at;
          entry.closes_at = oh.closes_at?.slice(0, 5) ?? oh.closes_at;
          entry.is_closed = oh.is_closed;
        }
      }
      operatingHours.value = base;
    }
  },
  { immediate: true }
);

watch(
  () => props.existingCoverUrl,
  (url) => {
    currentCoverUrl.value = url ?? '';
  }
);

const optionalTrimmedStringSchema = z.preprocess((value) => {
  if (value === null || value === undefined) return undefined;
  const normalized = String(value).trim();
  return normalized.length ? normalized : undefined;
}, z.string().optional());

const hubFormSchema = z.object({
  name: z.string().trim().min(1, 'Hub name is required.'),
  username: z
    .string()
    .trim()
    .min(3, 'Hub username must be at least 3 characters.')
    .max(30, 'Hub username must be 30 characters or fewer.')
    .regex(
      /^(?![0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$)[a-z0-9]+(?:-[a-z0-9]+)*$/,
      'Use lowercase letters, numbers, and hyphens only.'
    ),
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
        platform: z.enum(LINK_PLATFORMS),
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

watch(
  () => form.name,
  (value) => {
    if (!usernameTouched.value) {
      form.username = normalizeHubUsernameDraft(value);
    }
  }
);

watch(
  () => form.username,
  (value) => {
    if (value !== verifiedUsername.value) {
      if (usernameStatus.value === 'verified') {
        usernameStatus.value = 'idle';
        usernameMessage.value = 'Please verify this username before saving.';
      } else if (usernameStatus.value !== 'checking') {
        usernameStatus.value = 'idle';
        usernameMessage.value = '';
      }
    }
  }
);

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

function contactTypeIcon(type: 'mobile' | 'landline') {
  return CONTACT_TYPE_OPTIONS.find((option) => option.value === type)?.icon
    ?? 'i-heroicons-phone';
}

function contactTypeLabel(type: 'mobile' | 'landline') {
  return CONTACT_TYPE_OPTIONS.find((option) => option.value === type)?.label
    ?? 'Contact type';
}

function contactNumberError(index: number) {
  return (
    fieldErrors.value[`contact_numbers.${index}.number`]?.[0] ??
    fieldErrors.value[`contact_numbers.${index}.type`]?.[0]
  );
}

function websiteError(index: number) {
  return (
    fieldErrors.value[`websites.${index}.url`]?.[0] ??
    fieldErrors.value[`websites.${index}.platform`]?.[0]
  );
}

const websiteErrors = computed(() =>
  form.websites.map((_, index) => websiteError(index))
);

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

function onGalleryFilesUpdate(value: File | File[] | null) {
  newGalleryImages.value = Array.isArray(value) ? value : value ? [value] : [];
}

function onUsernameInput(value: string | number) {
  usernameTouched.value = true;
  form.username = String(value);
}

async function verifyUsername() {
  const normalized = normalizeHubUsernameDraft(form.username);
  form.username = normalized;

  if (!normalized) {
    usernameStatus.value = 'invalid';
    usernameMessage.value = 'Enter at least 3 lowercase letters or numbers.';
    verifiedUsername.value = '';
    return;
  }

  usernameStatus.value = 'checking';
  usernameMessage.value = 'Checking availability...';

  try {
    const result = await checkHubUsernameAvailability(normalized);
    form.username = result.username;
    verifiedUsername.value = result.username;
    usernameStatus.value = 'verified';
    usernameMessage.value = result.message ?? 'Username verified.';
  } catch (e: unknown) {
    const err = e as {
      data?: { errors?: Record<string, string[]>; message?: string };
    };
    const message =
      err.data?.errors?.username?.[0] ??
      err.data?.message ??
      'Unable to verify username right now.';

    usernameStatus.value = message.toLowerCase().includes('taken')
      ? 'taken'
      : 'invalid';
    usernameMessage.value = message;
    verifiedUsername.value = '';
  }
}

const usernameHint = computed(() => {
  if (fieldError('username')) return fieldError('username');
  return usernameMessage.value;
});


function handleSubmit() {
  fieldErrors.value = {};

  const parsed = hubFormSchema.safeParse({
    name: form.name,
    username: form.username,
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

  if (verifiedUsername.value !== parsed.data.username) {
    usernameStatus.value = 'invalid';
    usernameMessage.value = 'Verify the hub username before saving.';
    toast.add({
      title: 'Hub username verification required.',
      color: 'error'
    });
    return;
  }

  emit('submit', {
    name: parsed.data.name,
    username: parsed.data.username,
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
    contact_numbers: parsed.data.contact_numbers ?? [],
    websites: parsed.data.websites ?? [],
    coverImage: coverImage.value,
    galleryImages: newGalleryImages.value,
    removeGalleryImageIds: removeGalleryImageIds.value,
    is_active: parsed.data.is_active,
    operating_hours: operatingHours.value.map((oh) => ({ ...oh }))
  });
}

const footerRef = ref<HTMLElement | null>(null);
const footerVisible = ref(true);

let footerObserver: IntersectionObserver | null = null;

onMounted(() => {
  footerObserver = new IntersectionObserver(
    ([entry]) => {
      footerVisible.value = entry!.isIntersecting;
    },
    { threshold: 0 }
  );
  if (footerRef.value) footerObserver.observe(footerRef.value);
});

onUnmounted(() => {
  footerObserver?.disconnect();
  if (coverPreview.value) {
    URL.revokeObjectURL(coverPreview.value);
  }
});
</script>

<template>
  <form class="space-y-0" @submit.prevent="handleSubmit">
    <!-- ── Basic Info ─────────────────────────────────────────── -->
    <section id="section-basic" class="pb-8">
      <USeparator
        label="Basic Info"
        :ui="{ label: 'text-base font-semibold text-[var(--aktiv-ink)] px-3' }"
        class="mb-6"
      />
      <div class="space-y-5">
        <UFormField label="Hub Name" required :error="fieldError('name')">
          <UInput
            v-model="form.name"
            placeholder="e.g. Sunnyvale Tennis Club"
            required
            class="w-full"
          />
        </UFormField>

        <UFormField label="Username" required :error="fieldError('username')">
          <div class="flex items-start gap-3">
            <div class="min-w-0 flex-1">
              <UInput
                :model-value="form.username"
                placeholder="e.g. sunnyvale-tennis"
                class="w-full"
                @update:model-value="onUsernameInput"
              />
              <p
                v-if="usernameHint"
                class="mt-1 text-xs"
                :class="{
                  'text-[var(--aktiv-danger-fg)]':
                    usernameStatus === 'invalid' || usernameStatus === 'taken' || !!fieldError('username'),
                  'text-emerald-600': usernameStatus === 'verified',
                  'text-[var(--aktiv-muted)]':
                    usernameStatus === 'idle' || usernameStatus === 'checking'
                }"
              >
                {{ usernameHint }}
              </p>
              <p class="mt-1 text-xs text-[var(--aktiv-muted)]">
                Public link: {{ form.username ? `/hubs/${form.username}` : '/hubs/your-hub' }}
              </p>
            </div>

            <UButton
              type="button"
              color="neutral"
              variant="soft"
              :loading="usernameStatus === 'checking'"
              :disabled="!form.username.trim()"
              @click="verifyUsername"
            >
              Verify
            </UButton>
          </div>
        </UFormField>

        <UFormField label="Description" :error="fieldError('description')">
          <UTextarea
            v-model="form.description"
            placeholder="Brief description of your hub (optional)"
            :rows="3"
            class="w-full"
          />
        </UFormField>

        <div class="grid grid-cols-1 gap-6">
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
              <UDropdownMenu
                :items="
                  CONTACT_TYPE_OPTIONS.map((option) => ({
                    label: option.label,
                    icon: option.icon,
                    onSelect: () => {
                      entry.type = option.value;
                    }
                  }))
                "
              >
                <UButton
                  variant="ghost"
                  color="neutral"
                  class="w-9 shrink-0 justify-center border border-[var(--aktiv-border)] px-0"
                  :aria-label="contactTypeLabel(entry.type)"
                >
                  <UIcon
                    :name="contactTypeIcon(entry.type)"
                    class="h-4 w-4 text-[var(--aktiv-muted)]"
                  />
                </UButton>
              </UDropdownMenu>
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

          <!-- Links -->
          <div class="space-y-2">
            <p class="text-sm font-medium text-[var(--aktiv-ink)]">
              Links
              <span class="ml-1 text-xs font-normal text-[var(--aktiv-muted)]"
                >(optional, up to 5)</span
              >
            </p>

            <AppLinksEditor
              v-model="form.websites"
              :errors="websiteErrors"
              placeholder="https://example.com"
              add-label="Add another"
            />
          </div>
        </div>
      </div>
    </section>

    <!-- ── Location ───────────────────────────────────────────── -->
    <section id="section-location" class="pb-8">
      <USeparator
        label="Location"
        :ui="{ label: 'text-base font-semibold text-[var(--aktiv-ink)] px-3' }"
        class="mb-6"
      />
      <div class="space-y-4">
        <HubLocationPicker
          :model-value="{ lat: form.lat, lng: form.lng }"
          @update:model-value="onPinUpdate"
        />

        <p
          v-if="fieldError('lat') || fieldError('lng')"
          class="text-xs text-[var(--aktiv-danger-fg)]"
        >
          {{ fieldError('lat') ?? fieldError('lng') }}
        </p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <UFormField
            label="Address 1"
            required
            :error="fieldError('address')"
            class="sm:col-span-2"
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
            class="sm:col-span-2"
          >
            <UInput
              v-model="form.address_line2"
              placeholder="Unit, floor, building, suite…"
              class="w-full"
            />
          </UFormField>

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

          <UFormField
            label="Landmark (optional)"
            :error="fieldError('landmark')"
            class="sm:col-span-2"
          >
            <UInput
              v-model="form.landmark"
              placeholder="e.g. near Petron station, behind SM Mall"
              class="w-full"
            />
          </UFormField>
        </div>
      </div>
    </section>

    <!-- ── Operating Hours ────────────────────────────────────── -->
    <section id="section-hours" class="pb-8">
      <USeparator
        label="Operating Hours"
        :ui="{ label: 'text-base font-semibold text-[var(--aktiv-ink)] px-3' }"
        class="mb-6"
      />
      <p class="mb-3 text-xs text-[var(--aktiv-muted)]">
        Set the opening and closing times for each day. These define the booking
        grid range.
      </p>

      <!-- Desktop table -->
      <div
        class="hidden overflow-hidden rounded-lg border border-[var(--aktiv-border)] sm:block"
      >
        <table class="w-full text-sm">
          <thead>
            <tr
              class="border-b border-[var(--aktiv-border)] bg-[var(--aktiv-surface)]"
            >
              <th
                class="py-2 pl-3 pr-2 text-left font-medium text-[var(--aktiv-muted)]"
              >
                Day
              </th>
              <th
                class="px-2 py-2 text-left font-medium text-[var(--aktiv-muted)]"
              >
                Opens
              </th>
              <th
                class="px-2 py-2 text-left font-medium text-[var(--aktiv-muted)]"
              >
                Closes
              </th>
              <th
                class="py-2 pl-2 pr-3 text-left font-medium text-[var(--aktiv-muted)]"
              >
                Closed
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="oh in operatingHours"
              :key="oh.day_of_week"
              class="border-b border-[var(--aktiv-border)] last:border-b-0"
              :class="oh.is_closed ? 'opacity-50' : ''"
            >
              <td class="py-2 pl-3 pr-2 font-medium text-[var(--aktiv-ink)]">
                {{ DAY_NAMES[oh.day_of_week] }}
              </td>
              <td class="px-2 py-1.5">
                <USelect
                  v-model="oh.opens_at"
                  :items="HOUR_OPTIONS"
                  :disabled="oh.is_closed"
                  class="w-28"
                />
              </td>
              <td class="px-2 py-1.5">
                <USelect
                  v-model="oh.closes_at"
                  :items="HOUR_OPTIONS"
                  :disabled="oh.is_closed"
                  class="w-28"
                />
              </td>
              <td class="py-1.5 pl-2 pr-3 text-center">
                <UCheckbox v-model="oh.is_closed" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile cards -->
      <div
        class="divide-y divide-[var(--aktiv-border)] rounded-lg border border-[var(--aktiv-border)] sm:hidden"
      >
        <div
          v-for="oh in operatingHours"
          :key="oh.day_of_week"
          class="px-3 py-3"
          :class="oh.is_closed ? 'opacity-50' : ''"
        >
          <div class="mb-2 flex items-center justify-between">
            <span class="text-sm font-medium text-[var(--aktiv-ink)]">{{
              DAY_NAMES[oh.day_of_week]
            }}</span>
            <label
              class="flex items-center gap-1.5 text-xs text-[var(--aktiv-muted)]"
            >
              <UCheckbox v-model="oh.is_closed" />
              Closed
            </label>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <p class="mb-1 text-xs text-[var(--aktiv-muted)]">Opens</p>
              <USelect
                v-model="oh.opens_at"
                :items="HOUR_OPTIONS"
                :disabled="oh.is_closed"
                class="w-full"
              />
            </div>
            <div>
              <p class="mb-1 text-xs text-[var(--aktiv-muted)]">Closes</p>
              <USelect
                v-model="oh.closes_at"
                :items="HOUR_OPTIONS"
                :disabled="oh.is_closed"
                class="w-full"
              />
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ── Media ──────────────────────────────────────────────── -->
    <section id="section-media" class="pb-8">
      <USeparator
        label="Media"
        :ui="{ label: 'text-base font-semibold text-[var(--aktiv-ink)] px-3' }"
        class="mb-6"
      />

      <!-- Cover Image -->
      <UFormField
        label="Cover Image (optional)"
        :error="fieldError('cover_image')"
        class="mb-5"
      >
        <AppImageUploader
          v-model="coverImage"
          :preview-url="currentCoverUrl || null"
          accept="image/jpeg,image/png,image/webp,image/gif"
          :max-mb="HUB_IMAGE_MAX_SIZE_MB"
          @clear="coverImage = null; currentCoverUrl = ''"
        />
      </UFormField>

      <!-- Gallery Images -->
      <UFormField label="Gallery Images" :error="fieldError('gallery_images')">
        <AppImageUploader
          :model-value="newGalleryImages"
          :preview-url="activeExistingGalleryUrls"
          accept="image/jpeg,image/png,image/webp,image/gif"
          :max-mb="HUB_IMAGE_MAX_SIZE_MB"
          :max-files="10"
          @update:model-value="onGalleryFilesUpdate"
          @remove-existing="removeExistingGallery"
        />
      </UFormField>
    </section>

    <!-- ── Visibility ─────────────────────────────────────────── -->
    <section id="section-status" class="pb-8">
      <USeparator
        label="Visibility"
        :ui="{ label: 'text-base font-semibold text-[var(--aktiv-ink)] px-3' }"
        class="mb-6"
      />
      <div class="space-y-1.5">
        <div class="flex items-center gap-3">
          <USwitch v-model="form.is_active" />
          <p class="text-sm font-medium text-[var(--aktiv-ink)]">
            {{ form.is_active ? 'Active' : 'Inactive' }}
          </p>
        </div>
        <p class="text-xs text-[var(--aktiv-muted)]">
          When active, this hub will appear in the public hub list. Deactivate
          it to hide it from visitors while you set things up.
        </p>
      </div>
    </section>

    <!-- ── Floating Footer ────────────────────────────────────── -->
    <!-- ── Footer (natural position — acts as sentinel) ──────── -->
    <div
      ref="footerRef"
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

    <!-- ── Floating footer (shown when natural footer is off-screen) ── -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="translate-y-full opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="translate-y-0 opacity-100"
      leave-to-class="translate-y-full opacity-0"
    >
      <div
        v-if="!footerVisible"
        class="fixed bottom-0 left-0 right-0 z-20 flex items-center justify-between gap-3 border-t border-[var(--aktiv-border)] bg-[var(--aktiv-surface)]/95 px-4 py-3 backdrop-blur-sm sm:px-6"
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
    </Transition>
  </form>
</template>
