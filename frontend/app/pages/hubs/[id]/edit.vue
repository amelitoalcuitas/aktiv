<script setup lang="ts">
import { z } from 'zod';
import type {
  Hub,
  Court,
  HubContactNumber,
  HubWebsite,
  OperatingHoursEntry
} from '~/types/hub';
import {
  HUB_IMAGE_MAX_BYTES,
  HUB_IMAGE_MAX_SIZE_MB
} from '~/composables/useHubs';
import { LINK_PLATFORMS } from '~/types/links';

definePageMeta({ middleware: 'auth', layout: 'dashboard-hub' });

const route = useRoute();
const { fetchHub, fetchCourts, updateHub, deleteHub } = useHubs();
const toast = useToast();

const hubId = computed(() => String(route.params.id));

const manageTabs = computed(() => [
  {
    label: 'Hub',
    icon: 'i-heroicons-building-storefront',
    to: `/hubs/${hubId.value}/edit`
  },
  {
    label: 'Courts',
    icon: 'i-heroicons-squares-2x2',
    to: `/hubs/${hubId.value}/courts`
  },
  {
    label: 'Bookings',
    icon: 'i-heroicons-calendar-days',
    to: `/hubs/${hubId.value}/bookings`
  },
  {
    label: 'Events',
    icon: 'i-heroicons-megaphone',
    to: `/hubs/${hubId.value}/events`
  },
  {
    label: 'Reviews',
    icon: 'i-heroicons-star',
    to: `/hubs/${hubId.value}/reviews`
  },
  {
    label: 'Settings',
    icon: 'i-heroicons-cog-6-tooth',
    to: `/hubs/${hubId.value}/settings`
  }
]);

// ── Load data ─────────────────────────────────────────────────────────────────
const loading = ref(false);
const loadingHub = ref(true);
const hubData = ref<Hub | null>(null);
const courts = ref<Court[]>([]);

async function loadHub() {
  loadingHub.value = true;
  try {
    const [hub, hubCourts] = await Promise.all([
      fetchHub(hubId.value),
      fetchCourts(hubId.value).catch(() => [])
    ]);
    hubData.value = hub;
    courts.value = hubCourts;
    populateForm(hub);
  } catch {
    toast.add({ title: 'Failed to load hub details.', color: 'error' });
    await navigateTo('/dashboard');
  } finally {
    loadingHub.value = false;
  }
}

loadHub();

// ── Form state ────────────────────────────────────────────────────────────────
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
  contact_numbers: [] as HubContactNumber[],
  websites: [] as HubWebsite[],
  is_active: true
});

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

// ── Cover image ───────────────────────────────────────────────────────────────
const coverFileInput = ref<HTMLInputElement | null>(null);
const pendingCoverFile = ref<File | null>(null);
const coverPreviewUrl = ref('');
const currentCoverUrl = ref('');
const showCoverOverlay = ref(false);

function onCoverInputChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0] ?? null;
  if (!file) return;
  if (file.size > HUB_IMAGE_MAX_BYTES) {
    toast.add({
      title: `Cover image must be ${HUB_IMAGE_MAX_SIZE_MB}MB or smaller.`,
      color: 'error'
    });
    return;
  }
  if (coverPreviewUrl.value) URL.revokeObjectURL(coverPreviewUrl.value);
  pendingCoverFile.value = file;
  coverPreviewUrl.value = URL.createObjectURL(file);
  if (coverFileInput.value) coverFileInput.value.value = '';
}

function clearCover() {
  if (coverPreviewUrl.value) URL.revokeObjectURL(coverPreviewUrl.value);
  pendingCoverFile.value = null;
  coverPreviewUrl.value = '';
  currentCoverUrl.value = '';
}

const displayCover = computed(
  () => coverPreviewUrl.value || currentCoverUrl.value
);

// ── Gallery ───────────────────────────────────────────────────────────────────
const removeGalleryImageIds = ref<number[]>([]);
const newGalleryImages = ref<File[]>([]);

const existingGallery = computed(() => hubData.value?.gallery_images ?? []);

const activeExistingGallery = computed(() =>
  existingGallery.value.filter(
    (img) => !removeGalleryImageIds.value.includes(Number(img.id))
  )
);

const activeExistingGalleryUrls = computed(() =>
  activeExistingGallery.value.map((img) => img.url)
);

function removeExistingGallery(index: number) {
  const img = activeExistingGallery.value[index];
  if (img) removeGalleryImageIds.value.push(Number(img.id));
}

function onGalleryFilesUpdate(value: File | File[] | null) {
  newGalleryImages.value = Array.isArray(value) ? value : value ? [value] : [];
}

// ── Populate form from hub data ───────────────────────────────────────────────
function populateForm(hub: Hub) {
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
  form.contact_numbers = hub.contact_numbers.map((c) => ({ ...c }));
  form.websites = (hub.websites ?? []).map((w) => ({
    platform: w.platform ?? 'other',
    url: w.url
  }));
  form.is_active = hub.is_active ?? true;
  currentCoverUrl.value = hub.cover_image_url ?? '';
  pendingCoverFile.value = null;
  if (coverPreviewUrl.value) {
    URL.revokeObjectURL(coverPreviewUrl.value);
    coverPreviewUrl.value = '';
  }
  removeGalleryImageIds.value = [];
  newGalleryImages.value = [];

  if (hub.operating_hours && hub.operating_hours.length > 0) {
    const base = defaultOperatingHours();
    for (const oh of hub.operating_hours) {
      const entry = base[oh.day_of_week];
      if (entry) {
        entry.opens_at = oh.opens_at?.slice(0, 5) ?? oh.opens_at;
        entry.closes_at = oh.closes_at?.slice(0, 5) ?? oh.closes_at;
        entry.is_closed = oh.is_closed;
      }
    }
    operatingHours.value = base;
  }
  // Snapshot taken after nextTick so computed refs (operatingHours) are settled
  nextTick(() => { savedSnapshot.value = formSnapshot(); });
}

// ── Validation ────────────────────────────────────────────────────────────────
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
    .refine((v) => v !== null, 'Please pin your hub location on the map.'),
  lng: z
    .number()
    .nullable()
    .refine((v) => v !== null, 'Please pin your hub location on the map.'),
  is_active: z.boolean(),
  contact_numbers: z
    .array(
      z
        .object({ type: z.enum(['mobile', 'landline']), number: z.string() })
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

function fieldError(field: string) {
  return fieldErrors.value[field]?.[0];
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

// ── Contact / Website helpers ─────────────────────────────────────────────────
function addContactNumber() {
  if (form.contact_numbers.length < 5)
    form.contact_numbers.push({ type: 'mobile', number: '' });
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

const websiteErrors = computed(() =>
  form.websites.map((_, index) => websiteError(index))
);

// ── Location picker ───────────────────────────────────────────────────────────
function onPinUpdate(coords: { lat: number | null; lng: number | null }) {
  form.lat = coords.lat;
  form.lng = coords.lng;
}

// ── Save ──────────────────────────────────────────────────────────────────────
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
    is_active: form.is_active,
    contact_numbers: form.contact_numbers,
    websites: form.websites
  });

  if (!parsed.success) {
    const nextErrors: Record<string, string[]> = {};
    for (const issue of parsed.error.issues) {
      const key = issue.path.join('.');
      if (!key) continue;
      if (!nextErrors[key]) nextErrors[key] = [];
      nextErrors[key].push(issue.message);
    }
    fieldErrors.value = nextErrors;
    toast.add({
      title: 'Please fix the highlighted fields before saving.',
      color: 'error'
    });
    return;
  }

  loading.value = true;
  try {
    await updateHub(hubId.value, {
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
      cover_image: pendingCoverFile.value,
      gallery_images: newGalleryImages.value,
      remove_gallery_image_ids: removeGalleryImageIds.value,
      contact_numbers: parsed.data.contact_numbers ?? [],
      websites: parsed.data.websites ?? [],
      is_active: parsed.data.is_active,
      operating_hours: operatingHours.value.map((oh) => ({ ...oh }))
    });
    toast.add({ title: 'Hub updated successfully!', color: 'success' });
    await loadHub();
  } catch (e: unknown) {
    const err = e as {
      data?: { message?: string; errors?: Record<string, string[]> };
    };
    if (err?.data?.errors) fieldErrors.value = err.data.errors;
    toast.add({
      title: err?.data?.message ?? 'Failed to update hub.',
      color: 'error'
    });
  } finally {
    loading.value = false;
  }
}

// ── Delete ────────────────────────────────────────────────────────────────────
const isDeleteOpen = ref(false);
const deleteLoading = ref(false);

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

// ── Unsaved changes detection ─────────────────────────────────────────────────
const savedSnapshot = ref('');

function formSnapshot() {
  return JSON.stringify({
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
    contact_numbers: form.contact_numbers,
    websites: form.websites,
    is_active: form.is_active,
    operating_hours: operatingHours.value,
    hasPendingCover: !!pendingCoverFile.value,
    removedCover: !currentCoverUrl.value && !pendingCoverFile.value,
    removeGalleryImageIds: removeGalleryImageIds.value,
    newGalleryCount: newGalleryImages.value.length
  });
}

const isDirty = computed(() => savedSnapshot.value !== '' && formSnapshot() !== savedSnapshot.value);

onBeforeRouteLeave(() => {
  if (isDirty.value) {
    return window.confirm('You have unsaved changes. Leave anyway?');
  }
});

function onBeforeUnload(e: BeforeUnloadEvent) {
  if (isDirty.value) {
    e.preventDefault();
  }
}

// ── Floating footer sentinel ──────────────────────────────────────────────────
const footerRef = ref<HTMLElement | null>(null);
const footerVisible = ref(true);
let footerObserver: IntersectionObserver | null = null;

watch(footerRef, (el) => {
  footerObserver?.disconnect();
  if (!el) return;
  footerObserver = new IntersectionObserver(
    ([entry]) => {
      footerVisible.value = entry!.isIntersecting;
    },
    { threshold: 0 }
  );
  footerObserver.observe(el);
});

onMounted(() => {
  window.addEventListener('beforeunload', onBeforeUnload);
});

onUnmounted(() => {
  window.removeEventListener('beforeunload', onBeforeUnload);
  footerObserver?.disconnect();
  if (coverPreviewUrl.value) URL.revokeObjectURL(coverPreviewUrl.value);
});
</script>

<template>
  <div>
    <HubTabNav :tabs="manageTabs" />

    <div
      v-if="loadingHub"
      class="flex min-h-[60vh] flex-col items-center justify-center gap-3 px-4 text-center text-[#64748b]"
    >
      <UIcon name="i-heroicons-arrow-path" class="h-8 w-8 animate-spin" />
      <div>
        <p class="text-sm font-medium text-[#0f1728]">Loading hub details...</p>
        <p class="text-sm">Please wait while we prepare the edit form.</p>
      </div>
    </div>

    <form v-else @submit.prevent="handleSubmit">
      <!-- ── Hero / Cover Image ─────────────────────────────────────────────── -->
      <section
        class="relative isolate overflow-hidden border-b border-[var(--aktiv-border)] group"
        @mouseenter="showCoverOverlay = true"
        @mouseleave="showCoverOverlay = false"
      >
        <img
          v-if="displayCover"
          :src="displayCover"
          :alt="form.name"
          class="h-[168px] w-full object-cover sm:h-[260px]"
        />
        <div
          v-else
          class="flex h-[168px] w-full items-center justify-center bg-[var(--aktiv-border)] sm:h-[260px]"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-16 w-16 text-[var(--aktiv-muted)] opacity-30"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="1"
          >
            <rect x="3" y="3" width="18" height="18" rx="2" />
            <circle cx="8.5" cy="8.5" r="1.5" />
            <path d="M21 15l-5-5L5 21" />
          </svg>
        </div>
        <div
          class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent"
        />

        <!-- Edit overlay (buttons centred) -->
        <div
          class="absolute inset-0 flex items-center justify-center gap-3 bg-black/40 transition-opacity duration-200"
          :class="showCoverOverlay ? 'opacity-100' : 'opacity-0'"
        >
          <button
            type="button"
            class="flex items-center gap-2 rounded-lg bg-white/90 px-3 py-2 text-sm font-medium text-[var(--aktiv-ink)] shadow hover:bg-white"
            @click="coverFileInput?.click()"
          >
            <UIcon name="i-heroicons-camera" class="h-4 w-4" />
            Change cover
          </button>
          <button
            v-if="displayCover"
            type="button"
            class="flex items-center gap-2 rounded-lg bg-white/90 px-3 py-2 text-sm font-medium text-red-600 shadow hover:bg-white"
            @click="clearCover"
          >
            <UIcon name="i-heroicons-trash" class="h-4 w-4" />
            Remove
          </button>
        </div>

        <input
          ref="coverFileInput"
          type="file"
          accept="image/jpeg,image/png,image/webp,image/gif"
          class="sr-only"
          @change="onCoverInputChange"
        />

        <!-- Hub name editable overlay (bottom-left, mirrors HubProfileHeader) -->
        <div class="absolute inset-x-0 bottom-0 px-4 pb-4 md:px-6">
          <div class="mx-auto w-full max-w-[1400px]">
            <input
              v-model="form.name"
              type="text"
              placeholder="Hub name"
              class="w-full max-w-lg bg-transparent text-2xl font-black leading-tight text-white placeholder-white/50 outline-none drop-shadow-md md:text-4xl"
              :class="
                fieldError('name')
                  ? 'border-b border-red-400'
                  : 'border-b border-white/30 focus:border-white/70'
              "
            />
            <p v-if="fieldError('name')" class="mt-0.5 text-xs text-red-300">
              {{ fieldError('name') }}
            </p>
          </div>
        </div>
      </section>

      <!-- ── Content ────────────────────────────────────────────────────────── -->
      <div class="mx-auto w-full max-w-[1400px] px-3 py-6 md:px-6">
        <div
          class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[2fr_1.2fr]"
        >
          <!-- Left: display/content card -->
          <div
            class="overflow-hidden rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] divide-y divide-[var(--aktiv-border)]"
          >
            <!-- 1. Gallery -->
            <div class="p-4 md:p-6">
              <h2 class="mb-3 text-lg font-bold text-[var(--aktiv-ink)]">
                Gallery
              </h2>
              <AppImageUploader
                :model-value="newGalleryImages"
                :preview-url="activeExistingGalleryUrls"
                accept="image/jpeg,image/png,image/webp,image/gif"
                :max-mb="HUB_IMAGE_MAX_SIZE_MB"
                :max-files="10"
                @update:model-value="onGalleryFilesUpdate"
                @remove-existing="removeExistingGallery"
              />
            </div>

            <!-- 2. About (description) -->
            <div class="p-4 md:p-6">
              <h2 class="mb-3 text-lg font-bold text-[var(--aktiv-ink)]">
                About this hub
              </h2>
              <UFormField :error="fieldError('description')">
                <UTextarea
                  v-model="form.description"
                  placeholder="Brief description of your hub (optional)"
                  :rows="4"
                  class="w-full"
                />
              </UFormField>
            </div>

            <!-- 3. Contact + Websites (2-col, mirrors about.vue) -->
            <div
              class="grid grid-cols-1 divide-y divide-[var(--aktiv-border)] sm:grid-cols-2 sm:divide-x sm:divide-y-0"
            >
              <!-- Contact Numbers -->
              <div class="p-4 md:p-6">
                <h2 class="mb-3 text-lg font-bold text-[var(--aktiv-ink)]">
                  Contact
                </h2>
                <div class="space-y-2">
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
              </div>

              <!-- Links -->
              <div class="p-4 md:p-6">
                <h2 class="mb-3 text-lg font-bold text-[var(--aktiv-ink)]">
                  Links
                </h2>
                <AppLinksEditor
                  v-model="form.websites"
                  :errors="websiteErrors"
                  placeholder="https://example.com"
                  add-label="Add another"
                />
              </div>
            </div>

            <!-- 4. Courts (read-only + manage link) -->
            <div class="p-4 md:p-6">
              <div class="mb-3 flex items-center justify-between gap-2">
                <h2 class="text-lg font-bold text-[var(--aktiv-ink)]">
                  Courts
                </h2>
                <UButton
                  :to="`/hubs/${hubId}/courts`"
                  size="xs"
                  variant="ghost"
                  color="primary"
                  icon="i-heroicons-pencil-square"
                >
                  Manage Courts
                </UButton>
              </div>

              <div
                v-if="courts && courts.length > 0"
                class="grid grid-cols-1 gap-2 sm:grid-cols-2"
              >
                <div
                  v-for="court in courts"
                  :key="court.id"
                  class="flex flex-col overflow-hidden rounded-xl border border-[var(--aktiv-border)]"
                >
                  <div
                    class="relative h-[140px] w-full shrink-0 overflow-hidden bg-[var(--aktiv-border)]"
                  >
                    <AppImageViewer
                      v-if="court.image_url"
                      :src="court.image_url"
                      :alt="court.name"
                      wrapper-class="h-full w-full"
                      image-class="h-full w-full object-cover"
                    />
                    <div
                      v-else
                      class="flex h-full w-full flex-col items-center justify-center gap-2 text-[var(--aktiv-muted)]"
                    >
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-10 w-10 opacity-40"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="1"
                      >
                        <rect x="3" y="3" width="18" height="18" rx="2" />
                        <circle cx="8.5" cy="8.5" r="1.5" />
                        <path d="M21 15l-5-5L5 21" />
                      </svg>
                      <span class="text-xs font-medium opacity-50"
                        >No photo</span
                      >
                    </div>
                  </div>

                  <div class="flex flex-1 flex-col gap-2 p-3">
                    <div
                      class="flex min-w-0 items-center justify-between gap-2"
                    >
                      <p
                        class="min-w-0 truncate font-semibold text-[var(--aktiv-ink)]"
                        :title="court.name"
                      >
                        {{ court.name }}
                      </p>
                      <span
                        class="font-bold text-[var(--aktiv-primary)] text-xl"
                      >
                        ₱{{
                          parseFloat(court.price_per_hour).toLocaleString(
                            'en-PH'
                          )
                        }}<span
                          class="font-normal text-sm text-[var(--aktiv-muted)]"
                          >/hr</span
                        >
                      </span>
                    </div>

                    <div
                      class="flex flex-wrap gap-x-3 text-sm gap-y-1 text-[var(--aktiv-muted)]"
                    >
                      <span class="inline-flex items-center gap-1">
                        <UIcon
                          :name="
                            court.indoor
                              ? 'i-heroicons-building-office-2'
                              : 'i-heroicons-sun'
                          "
                          class="h-4 w-4 shrink-0"
                        />
                        {{ court.indoor ? 'Indoor' : 'Outdoor' }}
                      </span>
                      <span
                        v-if="court.surface"
                        class="inline-flex items-center gap-1 capitalize"
                      >
                        <UIcon
                          name="i-heroicons-squares-2x2"
                          class="h-4 w-4 shrink-0"
                        />
                        {{ court.surface }}
                      </span>
                    </div>

                    <div
                      v-if="court.sports.length > 0"
                      class="flex flex-wrap gap-1"
                    >
                      <UBadge
                        v-for="sport in court.sports"
                        :key="sport"
                        :label="sport"
                        variant="subtle"
                        color="neutral"
                        class="capitalize text-xs"
                      />
                    </div>
                  </div>
                </div>
              </div>
              <p v-else class="text-sm text-[var(--aktiv-muted)]">
                No courts yet.
                <NuxtLink
                  :to="`/hubs/${hubId}/courts`"
                  class="text-[var(--aktiv-primary)] hover:underline"
                >
                  Add one →
                </NuxtLink>
              </p>
            </div>
          </div>

          <!-- Right: configuration cards -->
          <div class="space-y-4 lg:sticky lg:top-[152px]">
            <!-- Operating Hours -->
            <div
              class="overflow-hidden rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-4 md:p-6"
            >
              <h2 class="mb-3 text-lg font-bold text-[var(--aktiv-ink)]">
                Operating Hours
              </h2>
              <p class="mb-3 text-xs text-[var(--aktiv-muted)]">
                Set the opening and closing times for each day.
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
                      <td
                        class="py-2 pl-3 pr-2 font-medium text-[var(--aktiv-ink)]"
                      >
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
                      <p class="mb-1 text-xs text-[var(--aktiv-muted)]">
                        Opens
                      </p>
                      <USelect
                        v-model="oh.opens_at"
                        :items="HOUR_OPTIONS"
                        :disabled="oh.is_closed"
                        class="w-full"
                      />
                    </div>
                    <div>
                      <p class="mb-1 text-xs text-[var(--aktiv-muted)]">
                        Closes
                      </p>
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
            </div>

            <!-- Location -->
            <div
              class="overflow-hidden rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-4 md:p-6"
            >
              <h2 class="mb-3 text-lg font-bold text-[var(--aktiv-ink)]">
                Location
              </h2>
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
                      class="w-full"
                    />
                  </UFormField>
                  <UFormField
                    label="Province"
                    required
                    :error="fieldError('province')"
                  >
                    <UInput
                      v-model="form.province"
                      placeholder="e.g. Metro Manila"
                      class="w-full"
                    />
                  </UFormField>
                  <UFormField
                    label="Country"
                    required
                    :error="fieldError('country')"
                  >
                    <UInput
                      v-model="form.country"
                      placeholder="e.g. Philippines"
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
            </div>

            <!-- Visibility -->
            <div
              class="overflow-hidden rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-4 md:p-6"
            >
              <h2 class="mb-3 text-lg font-bold text-[var(--aktiv-ink)]">
                Visibility
              </h2>
              <div class="flex items-center gap-3">
                <USwitch v-model="form.is_active" />
                <p class="text-sm font-medium text-[var(--aktiv-ink)]">
                  {{ form.is_active ? 'Active' : 'Inactive' }}
                </p>
              </div>
              <p class="mt-1.5 text-xs text-[var(--aktiv-muted)]">
                When active, this hub appears in the public hub list.
              </p>
            </div>
          </div>
        </div>

        <!-- Footer sentinel -->
        <div
          ref="footerRef"
          class="mt-6 flex items-center justify-between gap-3 border-t border-[var(--aktiv-border)] pt-4"
        >
          <UButton
            type="button"
            icon="i-heroicons-trash"
            color="error"
            variant="ghost"
            @click="isDeleteOpen = true"
          >
            Delete Hub
          </UButton>
          <div class="flex gap-3">
            <UButton to="/dashboard/hubs" color="neutral" variant="ghost"
              >Cancel</UButton
            >
            <UButton
              type="submit"
              :loading="loading"
              class="bg-[var(--aktiv-primary)] font-semibold hover:bg-[var(--aktiv-primary-hover)]"
            >
              Save Changes
            </UButton>
          </div>
        </div>
      </div>

      <!-- Floating footer -->
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
          class="fixed bottom-0 left-0 right-0 z-20 flex items-center justify-between gap-3 border-t border-[var(--aktiv-border)] bg-[var(--aktiv-surface)]/95 px-4 py-3 backdrop-blur-sm sm:px-6 md:left-60"
        >
          <UButton
            type="button"
            icon="i-heroicons-trash"
            color="error"
            variant="ghost"
            @click="isDeleteOpen = true"
          >
            Delete Hub
          </UButton>
          <div class="flex gap-3">
            <UButton to="/dashboard/hubs" color="neutral" variant="ghost"
              >Cancel</UButton
            >
            <UButton
              type="submit"
              :loading="loading"
              class="bg-[var(--aktiv-primary)] font-semibold hover:bg-[var(--aktiv-primary-hover)]"
            >
              Save Changes
            </UButton>
          </div>
        </div>
      </Transition>
    </form>

    <!-- Delete Confirm Modal -->
    <AppModal
      v-model:open="isDeleteOpen"
      title="Delete Hub"
      :ui="{ content: 'max-w-sm' }"
      confirm="Delete Hub"
      confirm-color="error"
      :confirm-loading="deleteLoading"
      @confirm="confirmDelete"
    >
      <template #body>
        <p class="text-sm text-[var(--aktiv-ink)]">
          Are you sure you want to delete
          <strong>{{ hubData?.name }}</strong
          >? This will permanently remove the hub and all its courts.
        </p>
      </template>
    </AppModal>
  </div>
</template>
