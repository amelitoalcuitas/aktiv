<script setup lang="ts">
import type { HubRating } from '~/types/hub';

definePageMeta({ middleware: 'auth', layout: 'dashboard-hub' });

const route = useRoute();
const { fetchHubRatings, fetchHubRatingCourts, fetchHub } = useHubs();

const hubId = computed(() => String(route.params.id));

const manageTabs = computed(() => [
  { label: 'Hub', icon: 'i-heroicons-building-storefront', to: `/hubs/${hubId.value}/edit` },
  { label: 'Courts', icon: 'i-heroicons-squares-2x2', to: `/hubs/${hubId.value}/courts` },
  { label: 'Bookings', icon: 'i-heroicons-calendar-days', to: `/hubs/${hubId.value}/bookings` },
  { label: 'Events', icon: 'i-heroicons-megaphone', to: `/hubs/${hubId.value}/events` },
  { label: 'Reviews', icon: 'i-heroicons-star', to: `/hubs/${hubId.value}/reviews` },
  { label: 'Settings', icon: 'i-heroicons-cog-6-tooth', to: `/hubs/${hubId.value}/settings` }
]);

// ── Sort ─────────────────────────────────────────────────────
type SortOption = 'newest' | 'highest' | 'lowest';
const sort = ref<SortOption>('newest');
const sortOptions: { label: string; value: SortOption }[] = [
  { label: 'Newest', value: 'newest' },
  { label: 'Highest', value: 'highest' },
  { label: 'Lowest', value: 'lowest' }
];

// ── Hub summary (for rating breakdown) ───────────────────────
import type { Hub } from '~/types/hub';
const hubData = ref<Hub | null>(null);

// ── Court filter ─────────────────────────────────────────────
const availableCourts = ref<string[]>([]);
const activeCourt = ref<string | null>(null);

async function loadCourts() {
  try {
    availableCourts.value = await fetchHubRatingCourts(hubId.value);
  } catch {
    availableCourts.value = [];
  }
}

// ── List state ───────────────────────────────────────────────
const ratings = ref<HubRating[]>([]);
const nextCursor = ref<string | null>(null);
const loadingInitial = ref(false);
const loadingMore = ref(false);

async function loadInitial() {
  loadingInitial.value = true;
  ratings.value = [];
  nextCursor.value = null;
  try {
    const res = await fetchHubRatings(
      hubId.value,
      undefined,
      sort.value,
      activeCourt.value
    );
    ratings.value = res.data;
    nextCursor.value = res.next_cursor ?? null;
  } finally {
    loadingInitial.value = false;
  }
}

async function loadMore() {
  if (!nextCursor.value || loadingMore.value) return;
  loadingMore.value = true;
  try {
    const res = await fetchHubRatings(
      hubId.value,
      nextCursor.value,
      sort.value,
      activeCourt.value
    );
    ratings.value.push(...res.data);
    nextCursor.value = res.next_cursor ?? null;
  } finally {
    loadingMore.value = false;
  }
}

function onWindowScroll() {
  if (
    window.scrollY + window.innerHeight >=
    document.documentElement.scrollHeight - 300
  ) {
    loadMore();
  }
}

// ── Breakdown bars ───────────────────────────────────────────
const breakdown = computed(() => hubData.value?.rating_breakdown ?? null);
const totalForBreakdown = computed(() => {
  if (!breakdown.value) return 0;
  return Object.values(breakdown.value).reduce((a, b) => a + b, 0);
});

function barWidth(star: number): string {
  if (!breakdown.value || totalForBreakdown.value === 0) return '0%';
  return `${Math.round(((breakdown.value[star] ?? 0) / totalForBreakdown.value) * 100)}%`;
}

// ── Helpers ──────────────────────────────────────────────────
function relativeTime(iso: string): string {
  const diff = Date.now() - new Date(iso).getTime();
  const minutes = Math.floor(diff / 60000);
  if (minutes < 60) return minutes <= 1 ? 'just now' : `${minutes} minutes ago`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours} hour${hours > 1 ? 's' : ''} ago`;
  const days = Math.floor(hours / 24);
  if (days < 30) return `${days} day${days > 1 ? 's' : ''} ago`;
  const months = Math.floor(days / 30);
  if (months < 12) return `${months} month${months > 1 ? 's' : ''} ago`;
  const years = Math.floor(months / 12);
  return `${years} year${years > 1 ? 's' : ''} ago`;
}

// ── Watchers ─────────────────────────────────────────────────
watch(sort, () => loadInitial());
watch(activeCourt, () => loadInitial());

// ── Init ─────────────────────────────────────────────────────
onMounted(async () => {
  await Promise.all([
    loadInitial(),
    loadCourts(),
    fetchHub(hubId.value).then(h => { hubData.value = h; })
  ]);
  window.addEventListener('scroll', onWindowScroll);
});

onUnmounted(() => {
  window.removeEventListener('scroll', onWindowScroll);
});
</script>

<template>
  <div>
    <HubTabNav :tabs="manageTabs" />

    <div class="mx-auto w-full max-w-[1400px] px-4 py-8 md:px-6">
      <!-- Header row -->
      <div class="mb-6">
        <h1 class="text-xl font-bold text-[var(--aktiv-ink)]">Reviews</h1>
      </div>

      <!-- Summary header -->
      <div
        class="mb-4 rounded-xl border border-[var(--aktiv-border)] bg-white p-5"
      >
        <div class="flex items-start gap-6">
          <!-- Average score -->
          <div class="flex shrink-0 flex-col items-center">
            <span class="text-5xl font-black text-[var(--aktiv-ink)]">
              {{
                hubData?.rating != null
                  ? hubData.rating.toFixed(1)
                  : '–'
              }}
            </span>
            <div class="mt-1 flex gap-0.5">
              <UIcon
                v-for="star in 5"
                :key="star"
                :name="
                  star <= Math.round(hubData?.rating ?? 0)
                    ? 'i-heroicons-star-solid'
                    : 'i-heroicons-star'
                "
                class="h-4 w-4"
                :class="
                  star <= Math.round(hubData?.rating ?? 0)
                    ? 'text-[#F0A202]'
                    : 'text-[var(--aktiv-muted)]'
                "
              />
            </div>
            <span class="mt-1 text-xs text-[var(--aktiv-muted)]">
              {{ hubData?.reviews_count ?? 0 }} review{{
                hubData?.reviews_count !== 1 ? 's' : ''
              }}
            </span>
          </div>

          <!-- Bar breakdown -->
          <div class="flex flex-1 flex-col gap-1.5">
            <div
              v-for="star in [5, 4, 3, 2, 1]"
              :key="star"
              class="flex items-center gap-2"
            >
              <span class="w-3 text-right text-xs text-[var(--aktiv-muted)]">{{
                star
              }}</span>
              <UIcon
                name="i-heroicons-star-solid"
                class="h-3 w-3 shrink-0 text-[#F0A202]"
              />
              <div
                class="h-2 flex-1 overflow-hidden rounded-full bg-[var(--aktiv-border)]"
              >
                <div
                  class="h-full rounded-full bg-[#F0A202] transition-all duration-300"
                  :style="{ width: barWidth(star) }"
                />
              </div>
              <span class="w-5 text-right text-xs text-[var(--aktiv-muted)]">{{
                breakdown?.[star] ?? 0
              }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Court filter pills -->
      <div v-if="availableCourts.length > 0" class="mb-3 flex flex-wrap gap-2">
        <button
          type="button"
          class="rounded-full border px-3 py-1 text-xs font-semibold transition"
          :class="
            activeCourt === null
              ? 'border-[var(--aktiv-primary)] bg-[var(--aktiv-primary)] text-white'
              : 'border-[var(--aktiv-border)] text-[var(--aktiv-muted)] hover:border-[var(--aktiv-primary)] hover:text-[var(--aktiv-primary)]'
          "
          @click="activeCourt = null"
        >
          All Courts
        </button>
        <button
          v-for="court in availableCourts"
          :key="court"
          type="button"
          class="inline-flex items-center gap-1 rounded-full border px-3 py-1 text-xs font-semibold transition"
          :class="
            activeCourt === court
              ? 'border-[var(--aktiv-primary)] bg-[var(--aktiv-primary)] text-white'
              : 'border-[var(--aktiv-border)] text-[var(--aktiv-muted)] hover:border-[var(--aktiv-primary)] hover:text-[var(--aktiv-primary)]'
          "
          @click="activeCourt = court"
        >
          <UIcon name="i-lucide-land-plot" class="h-3 w-3" />
          {{ court }}
        </button>
      </div>

      <!-- Sort pills -->
      <div class="mb-4 flex gap-2">
        <button
          v-for="opt in sortOptions"
          :key="opt.value"
          type="button"
          class="rounded-full border px-3 py-1 text-xs font-semibold transition"
          :class="
            sort === opt.value
              ? 'border-[var(--aktiv-primary)] bg-[var(--aktiv-primary)] text-white'
              : 'border-[var(--aktiv-border)] text-[var(--aktiv-muted)] hover:border-[var(--aktiv-primary)] hover:text-[var(--aktiv-primary)]'
          "
          @click="sort = opt.value"
        >
          {{ opt.label }}
        </button>
      </div>

      <!-- Review list -->
      <div class="rounded-xl border border-[var(--aktiv-border)] bg-white">
        <!-- Skeleton -->
        <template v-if="loadingInitial">
          <div
            v-for="i in 4"
            :key="i"
            class="flex gap-3 border-b border-[var(--aktiv-border)] p-5 last:border-0"
          >
            <USkeleton class="h-9 w-9 shrink-0 rounded-full" />
            <div class="flex-1 space-y-2">
              <USkeleton class="h-3 w-28" />
              <USkeleton class="h-3 w-full" />
              <USkeleton class="h-3 w-2/3" />
            </div>
          </div>
        </template>

        <!-- Empty -->
        <div
          v-else-if="ratings.length === 0"
          class="py-16 text-center text-sm text-[var(--aktiv-muted)]"
        >
          No reviews yet.
        </div>

        <!-- Items -->
        <div v-else class="divide-y divide-[var(--aktiv-border)]">
          <div v-for="rating in ratings" :key="rating.id" class="p-5">
            <!-- Name + stars + time -->
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
              <span class="text-sm font-semibold text-[var(--aktiv-ink)]">{{
                rating.user.name
              }}</span>
              <div class="flex gap-0.5">
                <UIcon
                  v-for="star in 5"
                  :key="star"
                  :name="
                    star <= rating.rating
                      ? 'i-heroicons-star-solid'
                      : 'i-heroicons-star'
                  "
                  class="h-3.5 w-3.5"
                  :class="
                    star <= rating.rating
                      ? 'text-[#F0A202]'
                      : 'text-[var(--aktiv-muted)]'
                  "
                />
              </div>
              <span class="text-xs text-[var(--aktiv-muted)]">{{
                relativeTime(rating.created_at)
              }}</span>
            </div>

            <!-- Court badge -->
            <div v-if="rating.court_name" class="mt-1">
              <span
                class="inline-flex items-center gap-1 rounded bg-[var(--aktiv-border)] px-1.5 py-0.5 text-xs text-[var(--aktiv-muted)]"
              >
                <UIcon name="i-lucide-land-plot" class="h-3 w-3" />
                {{ rating.court_name }}
              </span>
            </div>

            <!-- Comment -->
            <p
              v-if="rating.comment"
              class="mt-1.5 text-sm text-[var(--aktiv-ink)]"
            >
              {{ rating.comment }}
            </p>
          </div>
        </div>
      </div>

      <!-- Load more spinner -->
      <div v-if="loadingMore" class="mt-6 flex justify-center">
        <UIcon
          name="i-heroicons-arrow-path"
          class="h-5 w-5 animate-spin text-[var(--aktiv-muted)]"
        />
      </div>
    </div>
  </div>
</template>
