<script setup lang="ts">
import type { Hub } from '~/types/hub';

definePageMeta({ layout: 'explore' });

const { applyRoute } = useHubOwnerRequest();
const { fetchHubsPaginated } = useHubs();

const featuredHubs = ref<Hub[]>([]);
const featuredHubsLoading = ref(true);

onMounted(async () => {
  try {
    const res = await fetchHubsPaginated({ sort: 'top', per_page: 3 });
    featuredHubs.value = Array.isArray(res?.data) ? res.data : [];
  } finally {
    featuredHubsLoading.value = false;
  }
});

const sports = [
  { label: 'Pickleball', value: 'pickleball' },
  { label: 'Badminton', value: 'badminton' },
  { label: 'Tennis', value: 'tennis' },
  { label: 'Basketball', value: 'basketball' },
  { label: 'Volleyball', value: 'volleyball' }
];

const features = [
  {
    number: '01',
    heading: 'Find Your Court',
    description:
      'Search by sport, city, or open now. Every hub, every court — right at your fingertips.'
  },
  {
    number: '02',
    heading: 'Book in Minutes',
    description:
      'Pick your time slots and lock in your court instantly. No calls, no waiting.'
  },
  {
    number: '03',
    heading: 'Compete & Rank',
    description:
      'Join tournaments, climb leaderboards, and track every win across every sport you play.'
  }
];

const steps = [
  { number: '01', label: 'Discover', description: 'Browse hubs near you' },
  { number: '02', label: 'Book', description: 'Lock in your slot' },
  { number: '03', label: 'Play', description: 'Show up and compete' }
];

const hubBenefits = [
  'Own your schedule',
  'Run tournaments',
  'Build your community'
];
</script>

<template>
  <div class="bg-[#f4f6f8] text-[#0f1728]">
    <!-- ── Hero ───────────────────────────────────────────────── -->
    <section class="relative overflow-hidden">
      <img
        src="https://images.unsplash.com/photo-1505484123437-c4ecb3e13bef?auto=format&fit=crop&w=1900&q=80"
        alt="Sports hub court"
        class="absolute inset-0 h-full w-full object-cover"
      />
      <div class="absolute inset-0 bg-[#dff1f7]/80 backdrop-brightness-95" />

      <div
        class="relative mx-auto flex min-h-screen w-full max-w-[1280px] flex-col items-center justify-center px-6 py-20 md:px-10"
      >
        <div class="mx-auto w-full max-w-[860px] text-center">
          <p
            class="m-0 text-sm font-bold uppercase tracking-[0.2em] text-[#0f5f9c]"
          >
            Discover Your Next Game
          </p>
          <h1
            class="mt-5 text-5xl font-black leading-[1.02] text-[#091427] sm:text-6xl lg:text-8xl"
          >
            Let&apos;s Make Every
            <span class="text-[#0f76bf]">Court&nbsp;Session</span>
            Count
          </h1>
          <p
            class="mx-auto mt-6 max-w-[620px] text-base leading-relaxed text-[#31425a] md:text-lg"
          >
            Explore top-rated hubs, compare courts, and secure your preferred
            slot in minutes.
          </p>
        </div>

        <div class="mt-10">
          <UButton
            to="/explore"
            size="xl"
            class="h-14 rounded-2xl bg-[#0f76bf] px-10 text-base font-bold text-white shadow-[0_16px_32px_rgba(12,102,167,0.3)] transition hover:bg-[#0b66a5]"
            :ui="{ base: 'justify-center' }"
          >
            Explore Now
          </UButton>
        </div>
      </div>
    </section>

    <!-- ── Why Aktiv ──────────────────────────────────────────── -->
    <section class="mx-auto max-w-[1280px] px-6 pt-28 pb-20 md:px-10">
      <div
        class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"
      >
        <h2
          class="text-4xl font-black leading-tight text-[#091427] sm:text-5xl lg:text-6xl"
        >
          Everything you need<br class="hidden sm:block" />
          to play.
        </h2>
        <p
          class="text-sm font-bold uppercase tracking-[0.2em] text-[#0f76bf] sm:pb-2"
        >
          What We Offer
        </p>
      </div>

      <div class="mt-12 divide-y divide-[#d6e6f4]">
        <div
          v-for="feature in features"
          :key="feature.number"
          class="group -mx-4 flex cursor-default flex-col gap-4 rounded-2xl px-4 py-10 transition-colors hover:bg-[#ecf4fc] md:flex-row md:items-center md:gap-0"
        >
          <!-- Number badge -->
          <div class="w-20 shrink-0">
            <span
              class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-[#0f76bf]/25 text-xs font-bold text-[#0f76bf] transition-colors group-hover:border-[#0f76bf] group-hover:bg-[#0f76bf] group-hover:text-white"
            >
              {{ feature.number }}
            </span>
          </div>
          <!-- Heading -->
          <h3
            class="flex-1 text-3xl font-black text-[#091427] transition-colors group-hover:text-[#0f76bf] sm:text-4xl lg:text-5xl"
          >
            {{ feature.heading }}
          </h3>
          <!-- Description + arrow -->
          <div class="flex items-center gap-4 md:max-w-[400px]">
            <p class="text-base leading-relaxed text-[#31425a] md:text-right">
              {{ feature.description }}
            </p>
            <span
              class="shrink-0 translate-x-0 text-xl text-[#0f76bf] opacity-0 transition-all group-hover:translate-x-1 group-hover:opacity-100"
              >→</span
            >
          </div>
        </div>
      </div>
    </section>

    <!-- ── Featured Hubs ─────────────────────────────────────── -->
    <div class="border-t border-[#d6e6f4]" />
    <section
      v-if="featuredHubsLoading || featuredHubs.length"
      class="mx-auto max-w-[1280px] px-6 pt-20 pb-24 md:px-10"
    >
      <div class="flex items-end justify-between">
        <h2 class="text-3xl font-black text-[#091427] sm:text-4xl">Top Hubs</h2>
        <NuxtLink
          to="/explore"
          class="text-sm font-bold text-[#0f76bf] hover:underline"
        >
          See all →
        </NuxtLink>
      </div>

      <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <!-- Skeletons while loading -->
        <template v-if="featuredHubsLoading">
          <div
            v-for="n in 3"
            :key="n"
            class="h-[340px] animate-pulse rounded-2xl bg-[#d6e6f4]"
          />
        </template>
        <!-- Hub cards -->
        <HubCard v-for="hub in featuredHubs" v-else :key="hub.id" :hub="hub" />
      </div>
    </section>

    <!-- ── Sports Strip ───────────────────────────────────────── -->
    <section class="border-y border-[#d6e6f4] bg-[#ecf4fc] py-20">
      <div class="mx-auto max-w-[1280px] px-6 md:px-10">
        <div class="flex items-center justify-between">
          <p
            class="text-sm font-bold uppercase tracking-[0.2em] text-[#0f76bf]"
          >
            Browse by Sport
          </p>
          <NuxtLink
            to="/explore"
            class="text-sm font-semibold text-[#31425a] transition hover:text-[#0f76bf]"
          >
            View all courts →
          </NuxtLink>
        </div>

        <div class="mt-8 flex flex-wrap gap-x-2 gap-y-1">
          <NuxtLink
            v-for="(sport, i) in sports"
            :key="sport.value"
            :to="`/explore?sport=${sport.value}`"
            class="group inline-flex items-baseline gap-2"
          >
            <span
              class="relative text-5xl font-black leading-none text-[#091427] transition-colors group-hover:text-[#0f76bf] sm:text-6xl lg:text-7xl"
            >
              {{ sport.label }}
              <span
                class="absolute bottom-1 left-0 h-[3px] w-0 rounded-full bg-[#0f76bf] transition-all duration-300 group-hover:w-full"
              />
            </span>
            <span
              v-if="(i as number) < sports.length - 1"
              class="text-4xl font-black text-[#0f76bf]/20 sm:text-5xl lg:text-6xl"
              >/</span
            >
          </NuxtLink>
        </div>
      </div>
    </section>

    <!-- ── How It Works ───────────────────────────────────────── -->
    <section class="mx-auto max-w-[1280px] px-6 py-24 md:px-10">
      <div
        class="mb-14 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"
      >
        <h2
          class="text-4xl font-black leading-tight text-[#091427] sm:text-5xl"
        >
          How it works
        </h2>
        <p
          class="text-sm font-bold uppercase tracking-[0.2em] text-[#0f76bf] sm:pb-1"
        >
          3 Simple Steps
        </p>
      </div>

      <!-- Mobile: stacked cards / Desktop: 3-col divided row -->
      <div class="flex flex-col gap-4 sm:grid sm:grid-cols-3 sm:gap-0 sm:divide-x sm:divide-[#d6e6f4]">
        <div
          v-for="step in steps"
          :key="step.number"
          class="flex flex-col rounded-2xl bg-[#ecf4fc] px-6 py-6 sm:rounded-none sm:bg-transparent sm:px-10 sm:py-10 sm:first:pl-0 sm:last:pr-0"
        >
          <span class="text-6xl font-black leading-none text-[#0f76bf]/30 sm:text-8xl">{{ step.number }}</span>
          <h3 class="mt-3 text-2xl font-black text-[#091427] sm:mt-4 sm:text-4xl">{{ step.label }}</h3>
          <p class="mt-1.5 text-sm text-[#31425a] sm:mt-2 sm:text-base">{{ step.description }}</p>
        </div>
      </div>
    </section>

    <!-- ── Start Your Hub ─────────────────────────────────────── -->
    <section class="bg-[#0f76bf] px-6 py-20 md:px-10">
      <div class="mx-auto max-w-[1280px]">
        <div class="flex flex-col gap-10 lg:flex-row lg:items-center lg:gap-16">
          <!-- Left: copy + CTA -->
          <div class="flex-1">
            <h2
              class="text-6xl font-black italic leading-[0.95] text-white sm:text-7xl lg:text-8xl xl:text-[6.5rem]"
            >
              START<br />YOUR HUB.<br />
              <span class="not-italic text-white/40">OWN THE GAME.</span>
            </h2>

            <p
              class="mt-8 max-w-[480px] text-base leading-relaxed text-white/70 md:text-lg"
            >
              Manage bookings, run tournaments, and grow your community — all
              from one dashboard.
            </p>

            <ul class="mt-10 flex flex-wrap gap-x-6 gap-y-2">
              <li
                v-for="benefit in hubBenefits"
                :key="benefit"
                class="text-sm font-semibold uppercase tracking-wide text-white/60"
              >
                {{ benefit }}
              </li>
            </ul>
          </div>

          <!-- Right: dark badge block + CTA -->
          <div class="flex shrink-0 flex-col items-center gap-6 lg:justify-end">
            <div
              class="flex h-64 w-64 flex-col items-center justify-center rounded-full bg-[#091427] text-center sm:h-72 sm:w-72 lg:h-80 lg:w-80"
            >
              <p
                class="text-sm font-bold uppercase tracking-[0.2em] text-[#4da6e0]"
              >
                Hub Management
              </p>
              <p
                class="mt-3 text-3xl font-black leading-tight text-white sm:text-4xl"
              >
                YOUR<br />COURTS.<br />YOUR<br />RULES.
              </p>
            </div>
            <UButton
              :to="applyRoute"
              size="xl"
              class="h-14 rounded-2xl bg-white px-8 text-base font-bold text-[#0f76bf] transition hover:bg-white/90"
              :ui="{ base: 'justify-center' }"
            >
              GET STARTED
            </UButton>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>
