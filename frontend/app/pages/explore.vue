<script setup lang="ts">
import type { Hub, PaginationMeta, SportType } from '~/types/hub';
import { isHubOpenNow } from '~/composables/useHubs';
import type { ApproximateLocation } from '~/composables/useApproximateLocation';

definePageMeta({ layout: 'explore' });

const { fetchHubsPaginated } = useHubs();
const { fetchApproximateLocation, getCachedApproximateLocation } =
  useApproximateLocation();
const route = useRoute();

// ── Filters ────────────────────────────────────────────────────────────────

const ALL_CITIES = '__all__';

// ── Draft state (what's shown in the UI) ───────────────────────────────────

const VALID_SPORTS: SportType[] = [
  'pickleball',
  'badminton',
  'tennis',
  'basketball',
  'volleyball'
];

function parseSportParam(): SportType[] {
  const raw = route.query.sport;
  const values = Array.isArray(raw) ? raw : raw ? [raw] : [];
  return values
    .flatMap((v) => (typeof v === 'string' ? v.split(',') : []))
    .filter((v): v is SportType => VALID_SPORTS.includes(v as SportType));
}

const initialSports = parseSportParam();

const searchInput = ref('');
const selectedCity = ref(ALL_CITIES);
const selectedSports = ref<SportType[]>(initialSports);
const openNow = ref(false);

// ── Applied state (what's sent to the API) ─────────────────────────────────
const appliedSearch = ref('');
const appliedCity = ref(ALL_CITIES);
const appliedSports = ref<SportType[]>(initialSports);
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
const topHubs = ref<Hub[]>([]);
const locationSource = ref<'approximate' | 'precise' | null>(null);
const approximateLocation = ref<ApproximateLocation | null>(null);
const locationDenied = ref(false);
const locationDismissed = ref(false);

async function loadNearbyHubs(
  lat: number,
  lng: number,
  source: 'approximate' | 'precise'
) {
  try {
    const result = await fetchHubsPaginated({
      lat,
      lng,
      sort: 'top',
      limit: 6
    });
    nearbyHubs.value = result.data;
    locationSource.value = result.data.length > 0 ? source : null;
  } catch {
    // silently ignore
  }
}

async function loadTopHubs() {
  try {
    const result = await fetchHubsPaginated({ sort: 'top', limit: 9 });
    topHubs.value = result.data;
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

// When no search/filters are active and location is available, show only nearby hubs
const showNearbyOnly = computed(
  () => !hasActiveFilters.value && nearbyHubs.value.length > 0
);

// When no search/filters and no location, show "Top Hubs"
const showTopHubsOnly = computed(
  () =>
    !hasActiveFilters.value &&
    nearbyHubs.value.length === 0 &&
    topHubs.value.length > 0
);

const showLocationNotice = computed(
  () =>
    locationDenied.value &&
    !locationDismissed.value &&
    locationSource.value !== 'approximate'
);

const nearbyHeading = computed(() =>
  locationSource.value === 'precise' ? 'Hubs near you' : 'Hubs near your area'
);

async function applyApproximateLocation(location: ApproximateLocation) {
  approximateLocation.value = location;

  if (locationSource.value === 'precise') return;

  userLat.value = location.lat;
  userLng.value = location.lng;

  await loadNearbyHubs(location.lat, location.lng, 'approximate');
}

async function initializeApproximateLocation() {
  const cachedLocation = getCachedApproximateLocation();
  if (cachedLocation) {
    await applyApproximateLocation(cachedLocation);
    return;
  }

  const resolvedLocation = await fetchApproximateLocation();
  if (resolvedLocation) {
    await applyApproximateLocation(resolvedLocation);
  }
}

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

  loadTopHubs();
  void initializeApproximateLocation();

  if ('geolocation' in navigator) {
    navigator.geolocation.getCurrentPosition(
      async (pos) => {
        userLat.value = pos.coords.latitude;
        userLng.value = pos.coords.longitude;
        await loadNearbyHubs(
          pos.coords.latitude,
          pos.coords.longitude,
          'precise'
        );
      },
      () => {
        locationDenied.value = approximateLocation.value === null;
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
    <!-- Page header — mobile only; desktop heading lives inside the sidebar -->
    <div
      class="border-b border-[var(--aktiv-border)] bg-white px-4 py-6 md:px-8 lg:hidden"
    >
      <h1 class="text-3xl font-black text-[#0f1728] md:text-4xl">
        Explore <span class="text-[#0f76bf]">Hubs</span>
      </h1>
      <p class="mt-1 text-[15px] text-[#5d7086]">
        Find the perfect court near you.
      </p>
    </div>

    <div class="mx-auto flex max-w-[1400px] gap-6 px-4 pb-8 pt-8 md:px-8">
      <!-- ── Desktop filter sidebar ── -->
      <aside class="hidden w-72 shrink-0 lg:block">
        <div class="sticky top-8 flex flex-col gap-4">
          <div
            class="flex-1 min-h-0 overflow-y-auto rounded-2xl bg-white p-5 shadow-sm"
          >
            <!-- Heading -->
            <div
              class="-mx-5 -mt-5 rounded-t-2xl border-b border-[var(--aktiv-border)] px-5 pb-4 pt-5"
            >
              <h1 class="text-2xl font-black text-[#0f1728]">
                Explore <span class="text-[#0f76bf]">Hubs</span>
              </h1>
              <p class="mt-0.5 text-[13px] text-[#5d7086]">
                Find the perfect court near you.
              </p>
            </div>

            <div class="space-y-5 pt-5">
              <!-- Filters label + clear -->
              <div class="flex items-center justify-between">
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

              <HubFilterPanel
                v-model:search="searchInput"
                v-model:city="selectedCity"
                v-model:sports="selectedSports"
                v-model:open-now="openNow"
                :available-cities="availableCities"
                :has-active-filters="hasActiveFilters"
                @apply="applyFilters"
                @clear="clearFilters"
              />

              <UButton block @click="applyFilters">Update Results</UButton>
            </div>
          </div>

          <div class="mt-auto rounded-2xl bg-[#004e89] p-5">
            <p
              class="text-base font-black uppercase italic leading-tight text-white"
            >
              List your venue.
            </p>
            <p class="mt-1.5 text-xs leading-relaxed text-white/75">
              Maximize revenue with the premier network of sports facilities.
            </p>
            <NuxtLink to="/apply">
              <UButton
                block
                class="mt-4 bg-[#c84b11] font-bold uppercase tracking-wide hover:bg-[#b04010]"
                color="neutral"
                variant="solid"
              >
                Get Started
              </UButton>
            </NuxtLink>
          </div>
        </div>
      </aside>

      <!-- ── Main content ── -->
      <div class="min-w-0 flex-1">
        <!-- Location denied notice -->
        <div
          v-if="showLocationNotice"
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

        <!-- Nearby hubs (default view: no search/filters active, approximate or precise location available) -->
        <div v-if="showNearbyOnly" class="mb-8">
          <p
            class="mb-3 flex items-center gap-1.5 text-sm font-semibold text-[#5d7086]"
          >
            <UIcon name="i-heroicons-map-pin" class="h-4 w-4" />
            {{ nearbyHeading }}
          </p>
          <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <HubCard v-for="hub in nearbyHubs" :key="hub.id" :hub="hub" />
          </div>
        </div>

        <!-- Top Hubs (default view: no search/filters active, location unavailable) -->
        <div v-else-if="showTopHubsOnly" class="mb-8">
          <p
            class="mb-3 flex items-center gap-1.5 text-sm font-semibold text-[#5d7086]"
          >
            <UIcon name="i-heroicons-star" class="h-4 w-4" />
            Top Hubs
          </p>
          <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <HubCard v-for="hub in topHubs" :key="hub.id" :hub="hub" />
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
          <div class="flex-1 overflow-y-auto p-4 space-y-4">
            <HubFilterPanel
              v-model:search="searchInput"
              v-model:city="selectedCity"
              v-model:sports="selectedSports"
              v-model:open-now="openNow"
              :available-cities="availableCities"
              :has-active-filters="hasActiveFilters"
              @apply="applyFilters"
              @clear="clearFilters"
            />
            <UButton block @click="applyFilters">Update Results</UButton>
          </div>

          <!-- Bottom actions -->
          <div class="border-t border-[var(--aktiv-border)] p-4">
            <div class="rounded-2xl bg-[#004e89] p-4">
              <p
                class="text-sm font-black uppercase italic leading-tight text-white"
              >
                List your venue.
              </p>
              <p class="mt-1 text-xs leading-relaxed text-white/75">
                Maximize revenue with the premier network of sports facilities.
              </p>
              <NuxtLink to="/apply" @click="filtersOpen = false">
                <UButton
                  block
                  class="mt-3 bg-[#c84b11] font-bold uppercase tracking-wide hover:bg-[#b04010]"
                  color="neutral"
                  variant="solid"
                >
                  Get Started
                </UButton>
              </NuxtLink>
            </div>
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
