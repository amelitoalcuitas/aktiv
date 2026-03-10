<script setup lang="ts">
import type { Hub } from '~/types/hub';

const route = useRoute();

const mockHubs: Record<string, Hub> = {
  '1': {
    id: 1,
    name: 'Sunnyvale Pickleball Club',
    city: 'Sunnyvale, CA',
    description: 'Premier pickleball facility with state-of-the-art courts.',
    cover_image_url:
      'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1300&q=80',
    coverImageUrl:
      'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1300&q=80',
    courts_count: 12,
    courtsCount: 12,
    sports: ['pickleball', 'badminton'],
    lowest_price_per_hour: '650.00',
    lowestPricePerHour: 650,
    rating: 4.8,
    reviewsCount: 104,
    isOpenNow: true,
    address: 'Sunnyvale, CA',
    is_approved: true,
    is_verified: false,
    owner_id: 1,
    created_at: '2026-01-01',
    lat: null,
    lng: null
  },
  '2': {
    id: 2,
    name: 'Downtown Rec Center',
    city: 'Austin, TX',
    description: 'Community center with indoor and outdoor multi-sport courts.',
    cover_image_url:
      'https://images.unsplash.com/photo-1500375592092-40eb2168fd21?auto=format&fit=crop&w=1300&q=80',
    coverImageUrl:
      'https://images.unsplash.com/photo-1500375592092-40eb2168fd21?auto=format&fit=crop&w=1300&q=80',
    courts_count: 6,
    courtsCount: 6,
    sports: ['basketball', 'badminton'],
    lowest_price_per_hour: '520.00',
    lowestPricePerHour: 520,
    rating: 4.6,
    reviewsCount: 68,
    isOpenNow: true,
    address: 'Austin, TX',
    is_approved: true,
    is_verified: false,
    owner_id: 2,
    created_at: '2026-01-01',
    lat: null,
    lng: null
  },
  '3': {
    id: 3,
    name: 'Eastbay Court House',
    city: 'Oakland, CA',
    description: 'Indoor courts ideal for after-work matches and coaching.',
    cover_image_url:
      'https://images.unsplash.com/photo-1543357480-c60d40007a3f?auto=format&fit=crop&w=1300&q=80',
    coverImageUrl:
      'https://images.unsplash.com/photo-1543357480-c60d40007a3f?auto=format&fit=crop&w=1300&q=80',
    courts_count: 8,
    courtsCount: 8,
    sports: ['tennis', 'pickleball'],
    lowest_price_per_hour: '700.00',
    lowestPricePerHour: 700,
    rating: 4.7,
    reviewsCount: 91,
    isOpenNow: false,
    address: 'Oakland, CA',
    is_approved: true,
    is_verified: false,
    owner_id: 3,
    created_at: '2026-01-01',
    lat: null,
    lng: null
  }
};

const fallbackHub: Hub = {
  id: 1,
  name: 'Sunnyvale Pickleball Club',
  city: 'Sunnyvale, CA',
  description: 'Premier pickleball facility with state-of-the-art courts.',
  cover_image_url:
    'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1300&q=80',
  coverImageUrl:
    'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1300&q=80',
  courts_count: 12,
  courtsCount: 12,
  sports: ['pickleball', 'badminton'],
  lowest_price_per_hour: '650.00',
  lowestPricePerHour: 650,
  rating: 4.8,
  reviewsCount: 104,
  isOpenNow: true,
  address: 'Sunnyvale, CA',
  is_approved: true,
  is_verified: false,
  owner_id: 1,
  created_at: '2026-01-01',
  lat: null,
  lng: null
};

const activeHub = computed<Hub>(() => {
  const id = String(route.params.id || '').trim();
  return mockHubs[id] || fallbackHub;
});

const tabs = computed(() => {
  const id = String(route.params.id || activeHub.value.id);
  return [
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
</script>

<template>
  <div>
    <!-- Hero -->
    <section
      class="relative isolate overflow-hidden border-b border-[var(--aktiv-border)]"
    >
      <img
        :src="activeHub.coverImageUrl"
        :alt="activeHub.name"
        class="h-[260px] w-full object-cover"
      />
      <div class="absolute inset-0 bg-black/50"></div>

      <div class="absolute inset-x-0 bottom-0">
        <div class="mx-auto w-full max-w-[1160px] px-4 pb-6 md:px-6">
          <div class="max-w-[760px] p-5">
            <p
              class="m-0 inline-flex items-center gap-1.5 rounded-full bg-white/20 px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-white"
            >
              <UIcon name="i-heroicons-map-pin" class="h-4 w-4" />
              {{ activeHub.city }}
            </p>

            <h1
              class="mt-3 text-3xl font-black leading-tight text-white md:text-5xl"
            >
              {{ activeHub.name }}
            </h1>

            <div class="mt-4 flex flex-wrap items-center gap-2.5">
              <UBadge
                v-for="sport in activeHub.sports"
                :key="sport"
                variant="outline"
                color="neutral"
                class="border-white/35 bg-transparent text-white uppercase tracking-wide"
              >
                {{ sport }}
              </UBadge>

              <span
                class="inline-flex items-center gap-1 rounded-full bg-white/15 px-3 py-1 text-xs font-bold text-white"
              >
                <UIcon
                  name="i-heroicons-star-solid"
                  class="h-4 w-4 text-[var(--aktiv-accent)]"
                />
                {{ (activeHub.rating ?? 0).toFixed(1) }} ({{
                  activeHub.reviewsCount ?? 0
                }})
              </span>

              <UBadge
                :color="activeHub.isOpenNow ? 'success' : 'error'"
                variant="soft"
              >
                {{ activeHub.isOpenNow ? 'Open now' : 'Closed' }}
              </UBadge>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Tab navigation -->
    <nav
      class="sticky top-0 z-40 border-b border-[var(--aktiv-border)] bg-[var(--aktiv-surface)]"
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
  </div>
</template>
