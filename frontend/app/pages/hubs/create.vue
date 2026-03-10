<script setup lang="ts">
import { useAuth } from '~/composables/useAuth';
import { useHubs } from '~/composables/useHubs';

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
  cover_image_url: '',
  sports: [] as string[]
});

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

async function handleSubmit() {
  loading.value = true;
  fieldErrors.value = {};
  try {
    await createHub({
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
      cover_image_url: form.cover_image_url || null,
      sports: form.sports
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

          <!-- Cover Image URL -->
          <UFormField
            label="Cover Image URL (optional)"
            :error="fieldError('cover_image_url')"
          >
            <UInput
              v-model="form.cover_image_url"
              type="url"
              placeholder="https://example.com/photo.jpg"
              class="w-full"
            />
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
