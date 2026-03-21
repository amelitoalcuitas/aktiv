<script setup lang="ts">
import type { Hub } from '~/types/hub';
import { useAuthStore } from '~/stores/auth';

const route = useRoute();
const { fetchHub } = useHubs();
const authStore = useAuthStore();

const hubId = computed(() => String(route.params.id ?? ''));

const { data: activeHub, error: hubError } = await useAsyncData<Hub>(
  `hub-${hubId.value}`,
  () => fetchHub(hubId.value),
  {
    default: () =>
      ({
        id: 0,
        name: '',
        description: null,
        city: '',
        address: '',
        address_line2: null,
        landmark: null,
        zip_code: null,
        province: null,
        country: null,
        lat: null,
        lng: null,
        cover_image_url: null,
        gallery_images: [],
        is_approved: true,
        is_verified: false,
        is_active: true,
        owner_id: 0,
        sports: [],
        operating_hours: [],
        courts_count: 0,
        lowest_price_per_hour: null,
        created_at: ''
      }) as Hub
  }
);

// Any API error (including 404 for inactive hubs) should render the error page
if (hubError.value) {
  throw createError({
    statusCode: (hubError.value as { statusCode?: number }).statusCode ?? 404,
    statusMessage: 'Not Found',
    fatal: true
  });
}

const currentUserId = computed(() => authStore.user?.id ?? null);

const isInactiveOwnerView = computed(
  () =>
    !!activeHub.value?.id &&
    activeHub.value.is_active === false &&
    currentUserId.value === activeHub.value.owner_id
);

const tabs = computed(() => {
  const id = hubId.value || String(activeHub.value?.id ?? '');
  return [
    {
      label: 'About',
      icon: 'i-heroicons-information-circle',
      to: `/hubs/${id}/about`
    },
    {
      label: 'Scheduler',
      icon: 'i-heroicons-calendar-days',
      to: `/hubs/${id}/scheduler`
    },
    {
      label: 'Open Play',
      icon: 'i-heroicons-user-group',
      to: `/hubs/${id}/open-play`
    },
    {
      label: 'Tournaments',
      icon: 'i-heroicons-trophy',
      to: `/hubs/${id}/tournaments`
    },
    {
      label: 'Leaderboard',
      icon: 'i-heroicons-chart-bar-square',
      to: `/hubs/${id}/leaderboard`
    }
  ];
});

const isTabActive = (to: string) => route.path === to;

const isCurrentlyOpen = computed(() => {
  const hours = activeHub.value?.operating_hours;
  if (!hours?.length) return false;
  const now = new Date();
  const todayHours = hours.find((oh) => oh.day_of_week === now.getDay());
  if (!todayHours || todayHours.is_closed) return false;
  const [openH, openM] = todayHours.opens_at.split(':').map(Number);
  const [closeH, closeM] = todayHours.closes_at.split(':').map(Number);
  const nowMins = now.getHours() * 60 + now.getMinutes();
  return nowMins >= openH * 60 + openM && nowMins < closeH * 60 + closeM;
});

const coverImage = ref(
  activeHub.value?.cover_image_url ?? activeHub.value?.coverImageUrl ?? ''
);

function onCoverImgError() {
  coverImage.value = '';
}

const ratingsModalOpen = ref(false);
</script>

<template>
  <!-- Hero -->
    <section
      class="relative isolate overflow-hidden border-b border-[var(--aktiv-border)]"
    >
      <img
        v-if="coverImage"
        :src="coverImage"
        :alt="activeHub?.name"
        class="h-[260px] w-full object-cover"
        @error="onCoverImgError"
      />
      <div
        v-else
        class="flex h-[260px] w-full items-center justify-center bg-[var(--aktiv-border)]"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="h-20 w-20 text-[var(--aktiv-muted)] opacity-30"
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
      <div class="absolute inset-0 bg-black/50"></div>

      <div class="absolute inset-x-0 bottom-0">
        <div class="mx-auto w-full max-w-[1160px] px-4 pb-6 md:px-6">
          <div class="max-w-[760px] p-5">
            <div class="flex flex-wrap items-center gap-2">
              <p
                class="m-0 inline-flex items-center gap-1.5 rounded-full bg-white/20 px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-white"
              >
                <UIcon name="i-heroicons-map-pin" class="h-4 w-4" />
                {{ activeHub?.city }}
              </p>
              <p
                v-if="activeHub?.operating_hours?.length"
                class="m-0 inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold uppercase tracking-wide"
                :class="
                  isCurrentlyOpen
                    ? 'bg-green-500/25 text-green-300'
                    : 'bg-red-500/25 text-red-300'
                "
              >
                <span
                  class="h-1.5 w-1.5 rounded-full"
                  :class="isCurrentlyOpen ? 'bg-green-400' : 'bg-red-400'"
                />
                {{ isCurrentlyOpen ? 'Open Now' : 'Closed' }}
              </p>
            </div>

            <h1
              class="mt-3 text-3xl font-black leading-tight text-white md:text-5xl"
            >
              {{ activeHub?.name }}
            </h1>

            <div class="mt-4 flex flex-wrap items-center gap-2.5">
              <UBadge
                v-for="sport in activeHub?.sports ?? []"
                :key="sport"
                variant="outline"
                color="neutral"
                class="border-white/35 bg-transparent text-white uppercase tracking-wide"
              >
                {{ sport }}
              </UBadge>

              <button
                v-if="activeHub?.rating != null || (activeHub?.reviews_count ?? 0) >= 0"
                type="button"
                class="inline-flex items-center gap-1 rounded-full bg-white/15 px-3 py-1 text-xs font-bold text-white transition hover:bg-white/25"
                @click="ratingsModalOpen = true"
              >
                <UIcon
                  name="i-heroicons-star-solid"
                  class="h-4 w-4 text-[#F0A202]"
                />
                {{ activeHub.rating != null ? activeHub.rating.toFixed(1) : '–' }}
                ({{ activeHub.reviews_count ?? 0 }})
              </button>

              <HubRatingsModal
                v-if="activeHub?.id"
                v-model:open="ratingsModalOpen"
                :hub="activeHub"
              />

              <UBadge
                v-if="activeHub?.is_verified"
                color="primary"
                variant="soft"
              >
                Verified
              </UBadge>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Inactive hub banner (owner only) -->
    <div
      v-if="isInactiveOwnerView"
      class="border-b border-[var(--aktiv-border)] bg-[var(--aktiv-surface)]"
    >
      <div class="mx-auto w-full max-w-[1160px] px-4 py-3 md:px-6">
        <UAlert
          icon="i-heroicons-eye-slash"
          color="warning"
          variant="subtle"
          title="This hub is currently inactive"
          description="Only you (the owner) can see this page. This hub is hidden from the public listing. You can activate it from the hub edit page."
        />
      </div>
    </div>

    <!-- Tab navigation -->
    <nav
      class="sticky top-[76px] z-25 border-b border-[var(--aktiv-border)] bg-[var(--aktiv-surface)]"
    >
      <div
        class="mx-auto flex w-full max-w-[1160px] gap-6 overflow-x-auto px-4 md:px-6"
      >
        <NuxtLink
          v-for="tab in tabs"
          :key="tab.to"
          :to="tab.to"
          class="inline-flex items-center gap-2 border-b-2 py-4 text-sm font-bold whitespace-nowrap transition"
          :class="
            isTabActive(tab.to)
              ? 'border-[var(--aktiv-primary)] text-[var(--aktiv-primary)]'
              : 'border-transparent text-[var(--aktiv-muted)] hover:text-[var(--aktiv-ink)]'
          "
        >
          <UIcon :name="tab.icon" class="h-4 w-4" />
          {{ tab.label }}
        </NuxtLink>
      </div>
    </nav>
</template>
