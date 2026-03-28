<script setup lang="ts">
import type { Hub, PaginationMeta, SportType } from '~/types/hub';
import { isHubOpenNow } from '~/composables/useHubs';

definePageMeta({ layout: 'explore' });

const { fetchHubsPaginated } = useHubs();

// ── Filters ────────────────────────────────────────────────────────────────

const SPORT_OPTIONS: { value: SportType; label: string }[] = [
  { value: 'pickleball', label: 'Pickleball' },
  { value: 'badminton', label: 'Badminton' },
  { value: 'tennis', label: 'Tennis' },
  { value: 'basketball', label: 'Basketball' },
  { value: 'volleyball', label: 'Volleyball' }
];

const ALL_CITIES = '__all__';

// ── Draft state (what's shown in the UI) ───────────────────────────────────
const searchInput = ref('');
const selectedCity = ref(ALL_CITIES);
const selectedSports = ref<SportType[]>([]);
const openNow = ref(false);

// ── Applied state (what's sent to the API) ─────────────────────────────────
const appliedSearch = ref('');
const appliedCity = ref(ALL_CITIES);
const appliedSports = ref<SportType[]>([]);
const appliedOpenNow = ref(false);

// ── Pagination & data ──────────────────────────────────────────────────────

const hubs = ref<Hub[]>([]);
const suggestions = ref<Hub[]>([]);
const meta = ref<PaginationMeta | null>(null);
const loading = ref(false);
const loadError = ref(false);
const page = ref(1);

// ── Geolocation ────────────────────────────────────────────────────────────

const userLat = ref<number | null>(null);
const userLng = ref<number | null>(null);
const nearbyHubs = ref<Hub[]>([]);
const locationDenied = ref(false);
const locationDismissed = ref(false);

async function loadNearbyHubs(lat: number, lng: number) {
  try {
    const result = await fetchHubsPaginated({ lat, lng, limit: 6 });
    nearbyHubs.value = result.data;
  } catch {
    // silently ignore
  }
}

const hasMore = computed(
  () => !meta.value || page.value < meta.value.last_page
);

// Unique cities derived from loaded results for the city dropdown
const availableCities = computed(() => {
  const cities = hubs.value.map((h) => h.city).filter(Boolean);
  return [...new Set(cities)].sort();
});

// Client-side open-now filter applied on top of server results
const displayedHubs = computed(() => {
  if (!appliedOpenNow.value) return hubs.value;
  return hubs.value.filter((h) => isHubOpenNow(h));
});

// When no search/filters are active and location is available, show only "Near you"
const showNearbyOnly = computed(
  () => !hasActiveFilters.value && nearbyHubs.value.length > 0
);

async function loadPage(p: number) {
  if (loading.value) return;
  loading.value = true;
  loadError.value = false;
  try {
    const result = await fetchHubsPaginated({
      page: p,
      per_page: 12,
      search: appliedSearch.value || undefined,
      city: appliedCity.value !== ALL_CITIES ? appliedCity.value : undefined,
      sports: appliedSports.value.length ? appliedSports.value : undefined,
      lat: userLat.value ?? undefined,
      lng: userLng.value ?? undefined
    });
    if (p === 1) {
      hubs.value = result.data;
      suggestions.value = result.suggestions ?? [];
    } else {
      hubs.value = [...hubs.value, ...result.data];
    }
    meta.value = result.meta ?? null;
  } catch {
    loadError.value = true;
  } finally {
    loading.value = false;
  }
}

// Initial load
await loadPage(1);

// ── Infinite scroll ────────────────────────────────────────────────────────

const sentinel = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;

onMounted(() => {
  observer = new IntersectionObserver(
    (entries) => {
      if (entries[0]?.isIntersecting && hasMore.value && !loading.value) {
        page.value++;
        loadPage(page.value);
      }
    },
    { rootMargin: '200px' }
  );
  if (sentinel.value) observer.observe(sentinel.value);

  if ('geolocation' in navigator) {
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        userLat.value = pos.coords.latitude;
        userLng.value = pos.coords.longitude;
        loadNearbyHubs(pos.coords.latitude, pos.coords.longitude);
      },
      () => {
        locationDenied.value = true;
      }
    );
  }
});

onUnmounted(() => {
  observer?.disconnect();
});

// ── Filter actions ─────────────────────────────────────────────────────────

const filtersOpen = ref(false);

function applyFilters() {
  appliedSearch.value = searchInput.value;
  appliedCity.value = selectedCity.value;
  appliedSports.value = [...selectedSports.value];
  appliedOpenNow.value = openNow.value;
  suggestions.value = [];
  page.value = 1;
  loadPage(1);
  filtersOpen.value = false;
}

function onSearchEnter() {
  appliedSearch.value = searchInput.value;
  suggestions.value = [];
  page.value = 1;
  loadPage(1);
}

function clearFilters() {
  searchInput.value = '';
  selectedCity.value = ALL_CITIES;
  selectedSports.value = [];
  openNow.value = false;
  appliedSearch.value = '';
  appliedCity.value = ALL_CITIES;
  appliedSports.value = [];
  appliedOpenNow.value = false;
  suggestions.value = [];
  page.value = 1;
  loadPage(1);
  filtersOpen.value = false;
}

const hasActiveFilters = computed(
  () =>
    !!appliedSearch.value ||
    appliedCity.value !== ALL_CITIES ||
    appliedSports.value.length > 0 ||
    appliedOpenNow.value
);

const activeFilterCount = computed(
  () =>
    [appliedSearch.value, appliedOpenNow.value].filter(Boolean).length +
    (appliedCity.value !== ALL_CITIES ? 1 : 0) +
    appliedSports.value.length
);
</script>

<template>
  <div class="min-h-screen overflow-x-hidden bg-[#f4f6f8] pb-20 text-[#0f1728]">
    <!-- Page header — sits below the 76px overlay nav -->
    <div
      class="border-b border-[var(--aktiv-border)] bg-white px-4 pb-6 pt-[calc(76px+24px)] md:px-8"
    >
      <div class="mx-auto max-w-[1400px]">
        <h1 class="text-3xl font-black text-[#0f1728] md:text-4xl">
          Explore <span class="text-[#0f76bf]">Hubs</span>
        </h1>
        <p class="mt-1 text-[15px] text-[#5d7086]">
          Find the perfect court near you.
        </p>
      </div>
    </div>

    <div class="mx-auto flex max-w-[1400px] gap-6 px-4 py-8 md:px-8">
      <!-- ── Desktop filter panel (sticky left sidebar) ── -->
      <aside class="hidden w-64 shrink-0 lg:block">
        <div
          class="sticky top-24 rounded-2xl border border-[var(--aktiv-border)] bg-white p-5"
        >
          <div class="mb-4 flex items-center justify-between">
            <span class="text-sm font-bold text-[#0f1728]">Filters</span>
            <UButton
              v-if="hasActiveFilters"
              variant="ghost"
              size="xs"
              color="neutral"
              @click="clearFilters"
            >
              Clear all
            </UButton>
          </div>

          <!-- City -->
          <div class="mb-4">
            <label
              class="mb-1 block text-xs font-semibold uppercase tracking-wide text-[#5d7086]"
            >
              City
            </label>
            <USelect
              v-model="selectedCity"
              class="w-full"
              :items="[
                { label: 'All cities', value: ALL_CITIES },
                ...availableCities.map((c) => ({ label: c, value: c }))
              ]"
            />
          </div>

          <!-- Sport -->
          <div class="mb-4">
            <label
              class="mb-1 block text-xs font-semibold uppercase tracking-wide text-[#5d7086]"
            >
              Sport
            </label>
            <div class="flex flex-col gap-2">
              <label
                v-for="sport in SPORT_OPTIONS"
                :key="sport.value"
                class="flex cursor-pointer items-center gap-2 text-sm"
              >
                <UCheckbox
                  :model-value="selectedSports.includes(sport.value)"
                  @update:model-value="
                    selectedSports = $event
                      ? [...selectedSports, sport.value]
                      : selectedSports.filter((s) => s !== sport.value)
                  "
                />
                {{ sport.label }}
              </label>
            </div>
          </div>

          <!-- Open now -->
          <div class="mb-4">
            <label
              class="flex cursor-pointer items-center justify-between text-sm font-medium"
            >
              Open now
              <USwitch v-model="openNow" />
            </label>
          </div>

          <!-- Rating — coming soon -->
          <div
            class="rounded-xl border border-dashed border-[var(--aktiv-border)] p-3"
          >
            <div class="mb-1 flex items-center justify-between">
              <span class="text-sm font-medium text-[#5d7086]">Rating</span>
              <UBadge variant="soft" color="neutral" size="xs">Soon</UBadge>
            </div>
            <p class="text-xs text-[#8fa3b8]">
              Rating filters are coming in the next update.
            </p>
          </div>

          <UButton block class="mt-4" @click="applyFilters">
            Apply Filters
          </UButton>
        </div>
      </aside>

      <!-- ── Main content ── -->
      <div class="min-w-0 flex-1">
        <!-- Search bar -->
        <div class="mb-4">
          <UFieldGroup class="w-full">
            <UInput
              v-model="searchInput"
              placeholder="Search hubs..."
              class="w-full"
              @keyup.enter="onSearchEnter"
            />
            <UButton
              icon="i-heroicons-magnifying-glass"
              color="primary"
              class="px-4 sm:px-6"
              @click="onSearchEnter"
            />
          </UFieldGroup>
        </div>

        <!-- Location denied notice -->
        <div
          v-if="locationDenied && !locationDismissed"
          class="mb-4 flex items-center justify-between gap-3 rounded-xl border border-[var(--aktiv-border)] bg-white px-4 py-3 text-sm text-[#5d7086]"
        >
          <div class="flex items-center gap-2">
            <UIcon name="i-heroicons-map-pin" class="h-4 w-4 shrink-0" />
            <span>Enable location access to see hubs near you.</span>
          </div>
          <UButton
            variant="ghost"
            size="xs"
            icon="i-heroicons-x-mark"
            color="neutral"
            @click="locationDismissed = true"
          />
        </div>

        <!-- Mobile filter trigger row -->
        <div class="mb-4 flex items-center justify-between lg:hidden">
          <span class="text-sm text-[#5d7086]">
            {{ !showNearbyOnly && meta ? `${meta.total} hubs` : '' }}
          </span>
          <UButton
            variant="outline"
            size="sm"
            color="secondary"
            icon="i-heroicons-adjustments-horizontal"
            @click="filtersOpen = true"
          >
            Filters
            <UBadge
              v-if="activeFilterCount"
              color="secondary"
              size="xs"
              class="ml-1"
            >
              {{ activeFilterCount }}
            </UBadge>
          </UButton>
        </div>

        <!-- Result count (desktop) -->
        <p
          v-if="!showNearbyOnly"
          class="mb-4 hidden text-sm text-[#5d7086] lg:block"
        >
          {{ meta ? `${meta.total} hubs found` : '\u00a0' }}
        </p>

        <!-- Hubs near you (default view: no search/filters active) -->
        <div v-if="showNearbyOnly" class="mb-8">
          <p
            class="mb-3 flex items-center gap-1.5 text-sm font-semibold text-[#5d7086]"
          >
            <UIcon name="i-heroicons-map-pin" class="h-4 w-4" />
            Hubs near you
          </p>
          <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <HubCard v-for="hub in nearbyHubs" :key="hub.id" :hub="hub" />
          </div>
        </div>

        <!-- Hub grid (shown only when search/filters are active) -->
        <template v-else>
          <div
            v-if="displayedHubs.length > 0 || (loading && page === 1)"
            class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3"
          >
            <HubCard v-for="hub in displayedHubs" :key="hub.id" :hub="hub" />

            <!-- Skeleton cards on first load -->
            <template v-if="loading && page === 1">
              <div
                v-for="i in 6"
                :key="`skeleton-${i}`"
                class="h-[360px] animate-pulse rounded-2xl bg-[var(--aktiv-border)]"
              />
            </template>
          </div>

          <!-- Empty state -->
          <UCard
            v-else-if="!loading && !loadError"
            class="rounded-2xl border border-[#dde5ef] bg-white p-6"
            :ui="{ root: 'ring-0 divide-y-0' }"
          >
            <p class="font-medium text-[var(--aktiv-ink)]">No hubs found</p>
            <p class="mt-1 text-sm text-[var(--aktiv-muted)]">
              Try adjusting your filters or search a different city or sport.
            </p>
            <UButton
              v-if="hasActiveFilters"
              variant="outline"
              size="sm"
              class="mt-3"
              @click="clearFilters"
            >
              Clear filters
            </UButton>
          </UCard>

          <!-- Suggestions -->
          <div
            v-if="
              !loading &&
              !loadError &&
              displayedHubs.length === 0 &&
              appliedSearch &&
              suggestions.length > 0
            "
            class="mt-6"
          >
            <p class="mb-3 text-sm font-semibold text-[#5d7086]">
              Similar hubs you might like
            </p>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
              <HubCard v-for="hub in suggestions" :key="hub.id" :hub="hub" />
            </div>
          </div>

          <!-- Error state -->
          <UCard
            v-else-if="loadError"
            class="rounded-2xl border border-[#dde5ef] bg-white p-6"
            :ui="{ root: 'ring-0 divide-y-0' }"
          >
            <p class="text-[var(--aktiv-muted)]">
              Unable to load hubs. Please try again.
            </p>
          </UCard>
        </template>

        <!-- Loading more spinner -->
        <div v-if="loading && page > 1" class="mt-6 flex justify-center">
          <UIcon
            name="i-heroicons-arrow-path"
            class="h-6 w-6 animate-spin text-[var(--aktiv-primary)]"
          />
        </div>

        <!-- Infinite scroll sentinel -->
        <div ref="sentinel" class="h-1" />
      </div>
    </div>

    <!-- ── Mobile filter drawer (rendered only when open to avoid DOM leakage) ── -->
    <Transition name="fade">
      <div
        v-if="filtersOpen"
        class="fixed inset-0 z-50 flex lg:hidden"
        @click.self="filtersOpen = false"
      >
        <!-- Backdrop -->
        <div
          class="absolute inset-0 bg-black/40"
          @click="filtersOpen = false"
        />

        <!-- Panel -->
        <div
          class="relative ml-auto flex h-full w-72 flex-col bg-white shadow-xl"
        >
          <!-- Header -->
          <div
            class="flex items-center justify-between border-b border-[var(--aktiv-border)] px-4 py-4"
          >
            <span class="font-bold text-[#0f1728]">Filters</span>
            <div class="flex items-center gap-2">
              <UButton
                v-if="hasActiveFilters"
                variant="ghost"
                size="xs"
                color="neutral"
                @click="clearFilters"
              >
                Clear all
              </UButton>
              <UButton
                variant="ghost"
                size="xs"
                icon="i-heroicons-x-mark"
                @click="filtersOpen = false"
              />
            </div>
          </div>

          <!-- Filter content -->
          <div class="flex-1 overflow-y-auto space-y-5 p-4">
            <div>
              <label
                class="mb-1 block text-xs font-semibold uppercase tracking-wide text-[#5d7086]"
              >
                City
              </label>
              <USelect
                v-model="selectedCity"
                class="w-full"
                :items="[
                  { label: 'All cities', value: ALL_CITIES },
                  ...availableCities.map((c) => ({ label: c, value: c }))
                ]"
              />
            </div>

            <div>
              <label
                class="mb-1 block text-xs font-semibold uppercase tracking-wide text-[#5d7086]"
              >
                Sport
              </label>
              <div class="flex flex-col gap-2">
                <label
                  v-for="sport in SPORT_OPTIONS"
                  :key="sport.value"
                  class="flex cursor-pointer items-center gap-2 text-sm"
                >
                  <UCheckbox
                    :model-value="selectedSports.includes(sport.value)"
                    @update:model-value="
                      selectedSports = $event
                        ? [...selectedSports, sport.value]
                        : selectedSports.filter((s) => s !== sport.value)
                    "
                  />
                  {{ sport.label }}
                </label>
              </div>
            </div>

            <div>
              <label
                class="flex cursor-pointer items-center justify-between text-sm font-medium"
              >
                Open now
                <USwitch v-model="openNow" />
              </label>
            </div>

            <div
              class="rounded-xl border border-dashed border-[var(--aktiv-border)] p-3"
            >
              <div class="mb-1 flex items-center justify-between">
                <span class="text-sm font-medium text-[#5d7086]">Rating</span>
                <UBadge variant="soft" color="neutral" size="xs">Soon</UBadge>
              </div>
              <p class="text-xs text-[#8fa3b8]">Rating filters coming soon.</p>
            </div>
          </div>

          <!-- Footer -->
          <div class="border-t border-[var(--aktiv-border)] p-4">
            <UButton block @click="applyFilters">Apply Filters</UButton>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
