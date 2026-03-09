<script setup lang="ts">
import type { Hub } from '~/types/hub';

definePageMeta({ layout: 'explore' });

const search = ref('');

const hubs = ref<Hub[]>([
  {
    id: 'hub-1',
    name: 'Sunnyvale Pickleball Club',
    city: 'Sunnyvale, CA',
    description: 'Premier pickleball facility with state-of-the-art courts.',
    coverImageUrl:
      'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1300&q=80',
    courtsCount: 12,
    sports: ['pickleball', 'badminton'],
    lowestPricePerHour: 650,
    rating: 4.8,
    reviewsCount: 104,
    isOpenNow: true
  },
  {
    id: 'hub-2',
    name: 'Downtown Rec Center',
    city: 'Austin, TX',
    description: 'Community center with indoor and outdoor multi-sport courts.',
    coverImageUrl:
      'https://images.unsplash.com/photo-1500375592092-40eb2168fd21?auto=format&fit=crop&w=1300&q=80',
    courtsCount: 6,
    sports: ['basketball', 'badminton'],
    lowestPricePerHour: 520,
    rating: 4.6,
    reviewsCount: 68,
    isOpenNow: true
  },
  {
    id: 'hub-3',
    name: 'Eastbay Court House',
    city: 'Oakland, CA',
    description: 'Indoor courts ideal for after-work matches and coaching.',
    coverImageUrl:
      'https://images.unsplash.com/photo-1543357480-c60d40007a3f?auto=format&fit=crop&w=1300&q=80',
    courtsCount: 8,
    sports: ['tennis', 'pickleball'],
    lowestPricePerHour: 700,
    rating: 4.7,
    reviewsCount: 91,
    isOpenNow: false
  }
]);

const filteredHubs = computed(() => {
  const term = search.value.trim().toLowerCase();

  if (!term) {
    return hubs.value;
  }

  return hubs.value.filter((hub) => {
    return (
      hub.name.toLowerCase().includes(term) ||
      hub.city.toLowerCase().includes(term) ||
      hub.sports.some((sport) => sport.includes(term))
    );
  });
});
</script>

<template>
  <div class="bg-[#f4f6f8] pb-20 text-[#0f1728]">
    <section class="relative overflow-visible">
      <img
        src="https://images.unsplash.com/photo-1500375592092-40eb2168fd21?auto=format&fit=crop&w=1900&q=80"
        alt="Coastal city skyline"
        class="absolute inset-0 h-full w-full object-cover"
      />
      <div
        class="absolute inset-0 bg-[#dff1f7]/80 backdrop-brightness-95"
      ></div>

      <div
        class="relative mx-auto flex min-h-[620px] w-full max-w-[1280px] flex-col px-4 pb-40 pt-16 md:px-8 md:pt-24"
      >
        <div class="mx-auto w-full max-w-[840px] text-center">
          <p
            class="m-0 text-xs font-bold uppercase tracking-[0.28em] text-[#0f5f9c]"
          >
            Discover Your Next Game
          </p>
          <h1
            class="mt-5 text-4xl font-black leading-[1.05] text-[#091427] sm:text-5xl lg:text-7xl"
          >
            Let&apos;s Make Every
            <span class="text-[#0f76bf]">Court Session</span>
            Count
          </h1>
          <p
            class="mx-auto mt-5 max-w-[680px] text-base leading-relaxed text-[#31425a] md:text-lg"
          >
            Explore top-rated hubs, compare amenities, and secure your preferred
            court in minutes.
          </p>

          <label
            class="mx-auto mt-9 block w-full max-w-[540px]"
            for="hub-search"
          >
            <span class="sr-only">Search hubs</span>
            <UInput
              id="hub-search"
              v-model="search"
              type="text"
              placeholder="Search by city, sport, or hub name"
              size="xl"
              trailing-icon="i-heroicons-magnifying-glass"
              class="w-full"
              :ui="{
                base: 'h-14 rounded-2xl border border-[#d3dde8] bg-white/95 px-5 text-[15px] text-[#0f1728] shadow-[0_14px_30px_rgba(24,50,77,0.12)] ring-0 backdrop-blur-sm focus:border-[#0f76bf] focus:ring-0',
                trailing: 'pe-3 text-[#0f76bf]'
              }"
            />
          </label>
        </div>

        <div
          class="absolute bottom-0 left-1/2 z-20 w-full max-w-[1040px] -translate-x-1/2 translate-y-1/2 px-4 md:px-6"
        >
          <div
            class="grid gap-4 rounded-3xl border border-white/70 bg-white/95 p-4 shadow-[0_24px_45px_rgba(19,48,77,0.16)] backdrop-blur md:grid-cols-[1fr_1fr_auto] md:items-center md:gap-5 md:p-6"
          >
            <div class="rounded-2xl bg-[#f4f8fc] px-4 py-3">
              <p
                class="m-0 text-xs font-semibold uppercase tracking-wide text-[#66809d]"
              >
                Location
              </p>
              <p class="mt-1 text-base font-bold text-[#0f1728]">Any City</p>
            </div>

            <div class="rounded-2xl bg-[#f4f8fc] px-4 py-3">
              <p
                class="m-0 text-xs font-semibold uppercase tracking-wide text-[#66809d]"
              >
                Availability
              </p>
              <p class="mt-1 text-base font-bold text-[#0f1728]">
                Open This Week
              </p>
            </div>

            <UButton
              type="button"
              size="xl"
              class="h-14 rounded-2xl bg-[#0f76bf] px-8 text-base font-bold text-white shadow-[0_16px_32px_rgba(12,102,167,0.3)] transition hover:bg-[#0b66a5]"
              :ui="{ base: 'justify-center' }"
            >
              Explore Now
            </UButton>
          </div>
        </div>
      </div>
    </section>

    <section class="mx-auto w-full max-w-[1160px] px-4 pt-36 md:px-6">
      <div
        class="mb-8 flex flex-col items-start justify-between gap-4 md:flex-row md:items-end"
      >
        <div>
          <h2
            class="mt-2 text-3xl font-black leading-tight text-[#0f1728] md:text-5xl"
          >
            Explore <span class="text-[#0f76bf]">Hubs</span>
          </h2>
          <p class="mt-2 text-[15px] text-[#5d7086]">
            There are many places to play, choose your next destination.
          </p>
        </div>

        <UButton
          label="+ Add Hub"
          type="button"
          size="lg"
          class="rounded-2xl border border-[#d3deea] px-6 font-bold text-white shadow-[0_10px_24px_rgba(22,48,75,0.08)] transition"
          :ui="{ base: 'justify-center' }"
        />
      </div>

      <div class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
        <HubCard v-for="hub in filteredHubs" :key="hub.id" :hub="hub" />
      </div>

      <UCard
        v-if="filteredHubs.length === 0"
        class="mt-6 rounded-2xl border border-[#dde5ef] bg-white p-5 shadow-[0_14px_32px_rgba(15,33,64,0.08)]"
        :ui="{ root: 'ring-0 divide-y-0' }"
      >
        <p class="m-0 text-[var(--aktiv-muted)]">
          No hubs match your search yet.
        </p>
        <small class="mt-1 inline-block text-[var(--aktiv-muted)]"
          >Try a different city, sport, or hub name.</small
        >
      </UCard>
    </section>
  </div>
</template>
