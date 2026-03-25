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
        require_account_to_book: false,
        payment_methods: [],
        payment_qr_url: null,
        digital_bank_name: null,
        digital_bank_account: null,
        contact_numbers: [],
        websites: [],
        rating: null,
        reviews_count: 0,
        rating_breakdown: null,
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
      label: 'Overview',
      icon: 'i-heroicons-information-circle',
      to: `/hubs/${id}/about`
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
      class="h-[168px] sm:h-[260px] w-full object-cover"
      @error="onCoverImgError"
    />
    <div
      v-else
      class="flex h-[168px] w-full items-center justify-center bg-[var(--aktiv-border)]"
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
    ></div>

    <div class="absolute inset-x-0 bottom-0">
      <div
        class="mx-auto flex w-full max-w-[1400px] items-end justify-between px-4 pb-4 md:px-6"
      >
        <!-- Left: name + meta -->
        <div class="min-w-0 flex-1 pr-4">
          <h1
            class="text-2xl font-black leading-tight text-white drop-shadow-md md:text-4xl"
          >
            {{ activeHub?.name }}
          </h1>

          <p
            v-if="activeHub?.address || activeHub?.city"
            class="mt-1 text-sm text-white/70 drop-shadow"
          >
            {{ [activeHub?.address, activeHub?.city].filter(Boolean).join(', ') }}
          </p>

          <div class="mt-2 flex flex-wrap items-center gap-2">
            <!-- City + open/closed inline -->
            <span
              class="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-white"
            >
              <UIcon name="i-heroicons-map-pin" class="h-3.5 w-3.5" />
              {{ activeHub?.city }}
              <template v-if="activeHub?.operating_hours?.length">
                <span class="opacity-40">·</span>
                <span
                  class="inline-flex items-center gap-1 normal-case tracking-normal"
                  :class="
                    isCurrentlyOpen ? 'text-green-300' : 'text-red-300/80'
                  "
                >
                  <span
                    class="h-1.5 w-1.5 rounded-full"
                    :class="isCurrentlyOpen ? 'bg-green-400' : 'bg-red-400'"
                  />
                  {{ isCurrentlyOpen ? 'Open now' : 'Closed' }}
                </span>
              </template>
            </span>

            <!-- Rating -->
            <button
              v-if="
                activeHub?.rating != null ||
                (activeHub?.reviews_count ?? 0) >= 0
              "
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

            <!-- Verified -->
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
    <div class="mx-auto w-full max-w-[1400px] px-4 py-3 md:px-6">
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
    <div class="mx-auto flex w-full max-w-[1400px] items-center px-4 md:px-6">
      <!-- Tabs (scrollable) -->
      <div class="flex min-w-0 flex-1 overflow-x-auto">
        <NuxtLink
          v-for="tab in tabs"
          :key="tab.to"
          :to="tab.to"
          class="inline-flex items-center gap-2 border-b-[3px] px-3 py-4 text-sm font-bold whitespace-nowrap transition"
          :class="
            isTabActive(tab.to)
              ? 'border-[var(--aktiv-primary)] text-[var(--aktiv-primary)] bg-[var(--aktiv-primary)]/8'
              : 'border-transparent text-[var(--aktiv-muted)] hover:text-[var(--aktiv-ink)]'
          "
        >
          <UIcon :name="tab.icon" class="h-4 w-4" />
          {{ tab.label }}
        </NuxtLink>
      </div>

    </div>
  </nav>

</template>
