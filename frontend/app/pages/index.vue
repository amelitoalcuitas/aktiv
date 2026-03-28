<script setup lang="ts">
import type { Hub } from '~/types/hub';

definePageMeta({ layout: 'explore' });


const exploreSection = ref<HTMLElement | null>(null);

const { fetchHubsPaginated } = useHubs();

const { data: hubs, error: hubsError } = await useAsyncData<Hub[]>(
  'hubs-home',
  () => fetchHubsPaginated({ limit: 6, sort: 'top' }).then((r) => r.data),
  { default: () => [] as Hub[] }
);

const filteredHubs = computed(() => hubs.value ?? []);

const scrollToExploreSection = () => {
  exploreSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};
</script>

<template>
  <div class="bg-[#f4f6f8] pb-20 text-[#0f1728]">
    <section class="relative overflow-visible">
      <img
        src="https://images.unsplash.com/photo-1505484123437-c4ecb3e13bef?auto=format&fit=crop&w=1900&q=80"
        alt="Coastal city skyline"
        class="absolute inset-0 h-full w-full object-cover"
      />
      <div
        class="absolute inset-0 bg-[#dff1f7]/80 backdrop-brightness-95"
      ></div>

      <div
        class="relative mx-auto flex min-h-screen w-full max-w-[1280px] flex-col items-center justify-center px-4 py-16 md:px-8"
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
        </div>

        <div class="mt-10 flex w-full justify-center px-4 md:px-6">
          <UButton
            type="button"
            size="xl"
            @click="scrollToExploreSection"
            class="h-14 rounded-2xl bg-[#0f76bf] px-8 text-base font-bold text-white shadow-[0_16px_32px_rgba(12,102,167,0.3)] transition hover:bg-[#0b66a5]"
            :ui="{ base: 'justify-center' }"
          >
            Explore Now
          </UButton>
        </div>
      </div>
    </section>

    <section
      ref="exploreSection"
      class="mx-auto w-full max-w-[1160px] px-4 pt-16 md:px-6"
    >
      <div
        class="mb-8 flex flex-col items-start justify-between gap-4 md:flex-row md:items-end"
      >
        <div>
          <h2
            class="mt-2 text-3xl font-black leading-tight text-[#0f1728] md:text-5xl"
          >
            Top <span class="text-[#0f76bf]">Hubs</span>
          </h2>
          <p class="mt-2 text-[15px] text-[#5d7086]">
            The most active hubs in the city — pick your next court.
          </p>
        </div>

        <ULink
          to="#"
          class="text-[var(--aktiv-primary)] hover:text-[var(--aktiv-primary-hover)]"
        >
          Wanna add your own hub?
        </ULink>
      </div>

      <div class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
        <HubCard v-for="hub in filteredHubs" :key="hub.id" :hub="hub" />
      </div>

      <UCard
        v-if="hubsError"
        class="mt-6 rounded-2xl border border-[#dde5ef] bg-white p-5 shadow-[0_14px_32px_rgba(15,33,64,0.08)]"
        :ui="{ root: 'ring-0 divide-y-0' }"
      >
        <p class="m-0 text-[var(--aktiv-muted)]">
          Unable to load hubs right now. Please try again later.
        </p>
      </UCard>

      <UCard
        v-else-if="filteredHubs.length === 0"
        class="mt-6 rounded-2xl border border-[#dde5ef] bg-white p-5 shadow-[0_14px_32px_rgba(15,33,64,0.08)]"
        :ui="{ root: 'ring-0 divide-y-0' }"
      >
        <p class="m-0 text-[var(--aktiv-muted)]">
          Hubs are on their way!
        </p>
        <small class="mt-1 inline-block text-[var(--aktiv-muted)]"
          >We're just getting started — check back soon for courts near you.</small
        >
      </UCard>

      <div class="mt-10 flex justify-center">
        <ULink
          to="/explore"
          class="text-[var(--aktiv-primary)] hover:text-[var(--aktiv-primary-hover)]"
        >
          Browse All Hubs
        </ULink>
      </div>
    </section>
  </div>
</template>
