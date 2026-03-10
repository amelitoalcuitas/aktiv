<script setup lang="ts">
import maplibregl from 'maplibre-gl';
import type { Hub } from '~/types/hub';

definePageMeta({ layout: 'hub' });

const route = useRoute();
const { fetchHub } = useHubs();

const hubId = computed(() => String(route.params.id ?? ''));

const { data: hub, error } = await useAsyncData<Hub>(`hub-${hubId.value}`, () =>
  fetchHub(hubId.value)
);

// ── Address helpers ────────────────────────────────────────────────────────
const fullAddress = computed(() => {
  if (!hub.value) return '';
  const parts = [
    hub.value.address,
    hub.value.address_line2,
    hub.value.landmark ? `Near ${hub.value.landmark}` : null,
    hub.value.city,
    hub.value.province,
    hub.value.zip_code,
    hub.value.country
  ].filter(Boolean);
  return parts.join(', ');
});

// ── Map ───────────────────────────────────────────────────────────────────
const mapContainer = ref<HTMLElement | null>(null);
let map: maplibregl.Map | null = null;

const hasCoords = computed(
  () => hub.value?.lat != null && hub.value?.lng != null
);

onMounted(() => {
  if (!mapContainer.value || !hub.value?.lat || !hub.value?.lng) return;
  const lat = parseFloat(hub.value.lat);
  const lng = parseFloat(hub.value.lng);

  map = new maplibregl.Map({
    container: mapContainer.value,
    style: 'https://tiles.openfreemap.org/styles/bright',
    center: [lng, lat],
    zoom: 15,
    interactive: false
  });

  new maplibregl.Marker({ color: '#004e89' }).setLngLat([lng, lat]).addTo(map);
});

onUnmounted(() => {
  map?.remove();
  map = null;
});
</script>

<template>
  <div>
    <!-- Error -->
    <div
      v-if="error"
      class="rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-6 shadow-sm"
    >
      <p class="text-[var(--aktiv-muted)]">Failed to load hub details.</p>
    </div>

    <template v-else-if="hub">
      <!-- Card 1: Gallery -->
      <div
        v-if="hub.gallery_images.length > 0"
        class="overflow-hidden rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] shadow-sm"
      >
        <HubGallery :images="hub.gallery_images" :hub-name="hub.name" />
      </div>

      <!-- Card 2: Information -->
      <div
        class="space-y-6 rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-6 shadow-sm"
        :class="hub.gallery_images.length > 0 ? 'mt-6' : ''"
      >
        <!-- About / Description -->
        <div class="flex items-start gap-3">
          <UIcon
            name="i-heroicons-building-storefront"
            class="mt-0.5 h-5 w-5 shrink-0 text-[var(--aktiv-primary)]"
          />
          <div class="min-w-0">
            <h2 class="text-base font-bold text-[var(--aktiv-ink)]">About</h2>
            <p class="mt-1 text-sm leading-relaxed text-[var(--aktiv-muted)]">
              {{ hub.description || 'No description provided.' }}
            </p>
          </div>
        </div>

        <!-- Sports offered -->
        <div class="flex items-start gap-3">
          <UIcon
            name="i-heroicons-trophy"
            class="mt-0.5 h-5 w-5 shrink-0 text-[var(--aktiv-primary)]"
          />
          <div class="min-w-0">
            <h2 class="text-base font-bold text-[var(--aktiv-ink)]">
              Sports Offered
            </h2>
            <div v-if="hub.sports.length > 0" class="mt-2 flex flex-wrap gap-2">
              <UBadge
                v-for="sport in hub.sports"
                :key="sport"
                variant="outline"
                color="primary"
                class="capitalize"
              >
                {{ sport }}
              </UBadge>
            </div>
            <p v-else class="mt-1 text-sm text-[var(--aktiv-muted)]">
              No sports listed.
            </p>
          </div>
        </div>

        <!-- Courts summary -->
        <div class="flex items-start gap-3">
          <UIcon
            name="i-heroicons-rectangle-group"
            class="mt-0.5 h-5 w-5 shrink-0 text-[var(--aktiv-primary)]"
          />
          <div class="min-w-0">
            <h2 class="text-base font-bold text-[var(--aktiv-ink)]">Courts</h2>
            <div class="mt-2 flex gap-6">
              <div>
                <p class="text-2xl font-black text-[var(--aktiv-primary)]">
                  {{ hub.courts_count }}
                </p>
                <p class="text-xs text-[var(--aktiv-muted)]">Total courts</p>
              </div>
              <div v-if="hub.lowest_price_per_hour">
                <p class="text-2xl font-black text-[var(--aktiv-primary)]">
                  ₱{{
                    parseFloat(hub.lowest_price_per_hour).toLocaleString(
                      'en-PH'
                    )
                  }}
                </p>
                <p class="text-xs text-[var(--aktiv-muted)]">Starting / hr</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Card 3: Location -->
      <div
        class="mt-6 rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-6 shadow-sm"
      >
        <div class="flex items-start gap-3">
          <UIcon
            name="i-heroicons-map-pin"
            class="mt-0.5 h-5 w-5 shrink-0 text-[var(--aktiv-primary)]"
          />
          <div class="min-w-0 flex-1">
            <h2 class="text-base font-bold text-[var(--aktiv-ink)]">
              Location
            </h2>
            <p class="mt-1 text-sm text-[var(--aktiv-muted)]">
              {{ fullAddress || 'Address not available.' }}
            </p>
          </div>
        </div>
        <div
          v-if="hasCoords"
          ref="mapContainer"
          class="mt-4 h-56 w-full overflow-hidden rounded-xl border border-[var(--aktiv-border)]"
        />
      </div>
    </template>

    <!-- Loading skeleton -->
    <template v-else>
      <USkeleton class="h-[600px] w-full rounded-2xl" />
    </template>
  </div>
</template>
