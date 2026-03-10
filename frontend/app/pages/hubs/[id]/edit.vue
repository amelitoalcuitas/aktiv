<script setup lang="ts">
import { useAuth } from '~/composables/useAuth';
import { useHubs } from '~/composables/useHubs';

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
  form.cover_image_url = hub.cover_image_url ?? '';
  form.sports = [...hub.sports];
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
      cover_image_url: form.cover_image_url || null,
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
