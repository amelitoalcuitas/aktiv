<script setup lang="ts">
import maplibregl from 'maplibre-gl';
import type { Hub, Court } from '~/types/hub';

definePageMeta({ layout: 'hub' });

const route = useRoute();
const { fetchCourts } = useHubs();

const hubId = computed(() => String(route.params.id ?? ''));

const { data: hub } = useNuxtData<Hub>(`hub-${hubId.value}`);

const { data: courts, error } = await useAsyncData<Court[]>(
  `hub-courts-${hubId.value}`,
  () => fetchCourts(hubId.value)
);

// ── Open/closed status ────────────────────────────────────────────────────
const isCurrentlyOpen = computed(() => {
  if (!hub.value?.operating_hours?.length) return false;
  const now = new Date();
  const todayHours = hub.value.operating_hours.find(
    (oh) => oh.day_of_week === now.getDay()
  );
  if (!todayHours || todayHours.is_closed) return false;
  const [openH, openM] = todayHours.opens_at.split(':').map(Number);
  const [closeH, closeM] = todayHours.closes_at.split(':').map(Number);
  const nowMins = now.getHours() * 60 + now.getMinutes();
  return nowMins >= openH * 60 + openM && nowMins < closeH * 60 + closeM;
});

// ── Time helpers ──────────────────────────────────────────────────────────
function formatTime(time: string): string {
  const [h, m] = time.split(':').map(Number);
  const period = h >= 12 ? 'PM' : 'AM';
  const hour = h % 12 || 12;
  return m === 0
    ? `${hour} ${period}`
    : `${hour}:${String(m).padStart(2, '0')} ${period}`;
}

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
        class="rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-6 shadow-sm"
        :class="hub.gallery_images.length > 0 ? 'mt-6' : ''"
      >
        <div class="flex flex-col gap-6 sm:flex-row sm:items-start">
          <!-- Left column: all info sections -->
          <div class="min-w-0 flex-1 space-y-6">
            <!-- About / Description -->
            <div class="flex items-start gap-3">
              <UIcon
                name="i-heroicons-building-storefront"
                class="mt-0.5 h-5 w-5 shrink-0 text-[var(--aktiv-primary)]"
              />
              <div class="min-w-0">
                <h2 class="text-base font-bold text-[var(--aktiv-ink)]">
                  About
                </h2>
                <p
                  class="mt-1 whitespace-pre-wrap text-sm leading-relaxed text-[var(--aktiv-muted)]"
                >
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
                <div
                  v-if="hub.sports.length > 0"
                  class="mt-2 flex flex-wrap gap-2"
                >
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

            <!-- Contact Numbers -->
            <div
              v-if="hub.contact_numbers && hub.contact_numbers.length > 0"
              class="flex items-start gap-3"
            >
              <UIcon
                name="i-heroicons-phone"
                class="mt-0.5 h-5 w-5 shrink-0 text-[var(--aktiv-primary)]"
              />
              <div class="min-w-0">
                <h2 class="text-base font-bold text-[var(--aktiv-ink)]">
                  Contact
                </h2>
                <ul class="mt-2 space-y-1">
                  <li
                    v-for="(contact, i) in hub.contact_numbers"
                    :key="i"
                    class="flex items-center gap-2 text-sm text-[var(--aktiv-ink)]"
                  >
                    <UIcon
                      :name="
                        contact.type === 'mobile'
                          ? 'i-heroicons-device-phone-mobile'
                          : 'i-heroicons-phone'
                      "
                      class="h-4 w-4 shrink-0"
                    />
                    <ULink :href="`tel:${contact.number}`">{{
                      contact.number
                    }}</ULink>
                  </li>
                </ul>
              </div>
            </div>

            <!-- Websites -->
            <div
              v-if="hub.websites && hub.websites.length > 0"
              class="flex items-start gap-3"
            >
              <UIcon
                name="i-heroicons-globe-alt"
                class="mt-0.5 h-5 w-5 shrink-0 text-[var(--aktiv-primary)]"
              />
              <div class="min-w-0">
                <h2 class="text-base font-bold text-[var(--aktiv-ink)]">
                  Websites
                </h2>
                <ul class="mt-2 space-y-1">
                  <li v-for="(site, i) in hub.websites" :key="i">
                    <a
                      :href="site.url"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="text-sm text-[var(--aktiv-primary)] hover:underline break-all"
                      >{{ site.url }}</a
                    >
                  </li>
                </ul>
              </div>
            </div>

            <!-- Courts -->
            <div class="flex items-start gap-3">
              <UIcon
                name="i-heroicons-rectangle-group"
                class="mt-0.5 h-5 w-5 shrink-0 text-[var(--aktiv-primary)]"
              />
              <div class="min-w-0 flex-1">
                <h2 class="text-base font-bold text-[var(--aktiv-ink)]">
                  Courts
                </h2>
                <div
                  v-if="courts && courts.length > 0"
                  class="mt-3 grid gap-2 grid-cols-1 sm:grid-cols-[repeat(auto-fill,minmax(180px,240px))]"
                >
                  <NuxtLink
                    v-for="court in courts"
                    :key="court.id"
                    :to="`/hubs/${hubId}/scheduler?courtId=${court.id}`"
                    class="rounded-xl border border-[var(--aktiv-border)] bg-[var(--aktiv-bg)] p-3 flex flex-col gap-1.5 cursor-pointer transition hover:border-[var(--aktiv-primary)] hover:shadow-sm"
                  >
                    <div class="flex items-start justify-between gap-2">
                      <p
                        class="font-semibold text-sm text-[var(--aktiv-ink)] leading-tight"
                      >
                        {{ court.name }}
                      </p>
                      <UBadge
                        :label="court.indoor ? 'Indoor' : 'Outdoor'"
                        :color="court.indoor ? 'primary' : 'success'"
                        variant="subtle"
                        class="shrink-0"
                      />
                    </div>

                    <div
                      class="flex flex-wrap items-baseline gap-x-1.5 gap-y-0.5"
                    >
                      <span
                        class="text-base font-black text-[var(--aktiv-primary)]"
                      >
                        ₱{{
                          parseFloat(court.price_per_hour).toLocaleString(
                            'en-PH'
                          )
                        }}
                        <span
                          class="text-xs font-normal text-[var(--aktiv-muted)]"
                          >/ hr</span
                        >
                      </span>
                      <span
                        v-if="court.open_play_price_per_head"
                        class="text-xs text-[var(--aktiv-muted)]"
                      >
                        · ₱{{
                          parseFloat(
                            court.open_play_price_per_head
                          ).toLocaleString('en-PH')
                        }}/head
                      </span>
                    </div>

                    <div
                      class="flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-[var(--aktiv-muted)]"
                    >
                      <span
                        v-if="court.surface"
                        class="inline-flex items-center gap-1 capitalize"
                      >
                        <UIcon name="i-heroicons-squares-2x2" class="h-3 w-3" />
                        {{ court.surface }}
                      </span>
                      <span
                        v-if="court.max_players"
                        class="inline-flex items-center gap-1"
                      >
                        <UIcon name="i-heroicons-users" class="h-3 w-3" />
                        Max {{ court.max_players }}
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
                        variant="outline"
                        color="neutral"
                        class="capitalize"
                      />
                    </div>
                  </NuxtLink>
                </div>
                <p v-else class="mt-1 text-sm text-[var(--aktiv-muted)]">
                  No courts listed.
                </p>
              </div>
            </div>

            <!-- Schedule -->
            <div class="flex items-start gap-3">
              <UIcon
                name="i-heroicons-clock"
                class="mt-0.5 h-5 w-5 shrink-0 text-[var(--aktiv-primary)]"
              />
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                  <h2 class="text-base font-bold text-[var(--aktiv-ink)]">
                    Schedule
                  </h2>
                  <UBadge
                    :label="
                      isCurrentlyOpen ? 'Currently Open' : 'Currently Closed'
                    "
                    :color="isCurrentlyOpen ? 'success' : 'error'"
                    variant="subtle"
                  />
                </div>
                <div
                  v-if="hub.operating_hours && hub.operating_hours.length > 0"
                  class="mt-3 overflow-hidden rounded-xl border border-[var(--aktiv-border)] sm:max-w-sm"
                >
                  <div
                    v-for="(oh, index) in [...hub.operating_hours].sort(
                      (a, b) => a.day_of_week - b.day_of_week
                    )"
                    :key="oh.day_of_week"
                    class="flex items-center justify-between px-4 py-2.5 text-sm"
                    :class="
                      index % 2 === 0
                        ? 'bg-[var(--aktiv-bg)]'
                        : 'bg-[var(--aktiv-surface)]'
                    "
                  >
                    <span class="font-medium text-[var(--aktiv-ink)]">
                      {{
                        ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][
                          oh.day_of_week
                        ]
                      }}
                    </span>
                    <span
                      v-if="oh.is_closed"
                      class="rounded-full bg-[var(--aktiv-border)] px-2.5 py-0.5 text-xs font-medium text-[var(--aktiv-muted)]"
                      >Closed</span
                    >
                    <span v-else class="text-[var(--aktiv-muted)]">
                      {{ formatTime(oh.opens_at) }} –
                      {{ formatTime(oh.closes_at) }}
                    </span>
                  </div>
                </div>
                <p v-else class="mt-1 text-sm text-[var(--aktiv-muted)]">
                  No schedule listed.
                </p>
              </div>
            </div>
          </div>

          <!-- Right column: Location -->
          <div class="min-w-0 flex-1">
            <div class="flex items-start gap-3">
              <UIcon
                name="i-heroicons-map-pin"
                class="mt-0.5 h-5 w-5 shrink-0 text-[var(--aktiv-primary)]"
              />
              <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between gap-2">
                  <h2 class="text-base font-bold text-[var(--aktiv-ink)]">
                    Location
                  </h2>
                  <a
                    v-if="hasCoords"
                    :href="`https://maps.google.com/?q=${hub.lat},${hub.lng}`"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex shrink-0 items-center gap-1 text-xs font-medium text-[var(--aktiv-primary)] hover:underline"
                  >
                    <UIcon
                      name="i-heroicons-arrow-top-right-on-square"
                      class="h-3.5 w-3.5"
                    />
                    Maps
                  </a>
                </div>
                <p class="mt-1 text-sm text-[var(--aktiv-muted)]">
                  {{ fullAddress || 'Address not available.' }}
                </p>
              </div>
            </div>
            <div
              v-if="hasCoords"
              ref="mapContainer"
              class="mt-4 h-48 w-full overflow-hidden rounded-xl border border-[var(--aktiv-border)]"
            />
          </div>
        </div>
      </div>
    </template>

    <!-- Loading skeleton -->
    <template v-else>
      <USkeleton class="h-[600px] w-full rounded-2xl" />
    </template>
  </div>
</template>
