<script setup lang="ts">
import type { Hub } from '~/types/hub';

const props = defineProps<{
  hub: Hub;
}>();

const imgSrc = ref(props.hub.cover_image_url ?? props.hub.coverImageUrl ?? '');

function onImgError() {
  imgSrc.value = '';
}

const courtsCount = computed(
  () => props.hub.courts_count ?? props.hub.courtsCount ?? 0
);

const formattedPrice = computed(() => {
  const raw =
    props.hub.lowest_price_per_hour != null
      ? parseFloat(props.hub.lowest_price_per_hour)
      : (props.hub.lowestPricePerHour ?? 0);
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    maximumFractionDigits: 0
  }).format(raw);
});
</script>

<template>
  <NuxtLink :to="`/hubs/${hub.id}`" class="block h-full w-full min-w-0">
    <div
      class="flex h-full flex-col overflow-hidden rounded-2xl bg-white shadow-sm transition duration-150 ease-out hover:-translate-y-1 hover:scale-[1.02] hover:cursor-pointer hover:shadow-xl"
    >
      <!-- Cover image -->
      <div
        class="relative h-[200px] w-full shrink-0 overflow-hidden bg-gray-200"
      >
        <!-- Promo badge -->
        <div
          v-if="hub.has_active_promo"
          class="absolute right-3 top-3 z-10 flex items-center gap-1 rounded-full bg-orange-500 px-3 py-1 text-xs font-bold text-white shadow"
        >
          <UIcon name="i-heroicons-tag-solid" class="h-3 w-3" />
          PROMO
        </div>

        <img
          v-if="imgSrc"
          :src="imgSrc"
          :alt="hub.name"
          class="h-full w-full object-cover"
          @error="onImgError"
        />
        <div
          v-else
          class="flex h-full w-full flex-col items-center justify-center gap-2 text-gray-400"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-12 w-12 opacity-30"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="1"
          >
            <rect x="3" y="3" width="18" height="18" rx="2" />
            <circle cx="8.5" cy="8.5" r="1.5" />
            <path d="M21 15l-5-5L5 21" />
          </svg>
          <span class="text-xs font-medium opacity-40">No photo</span>
        </div>
      </div>

      <!-- Content -->
      <div class="flex flex-1 flex-col p-4">
        <!-- Name + rating -->
        <div class="flex items-start justify-between gap-2">
          <h3
            class="m-0 line-clamp-2 text-lg font-bold leading-tight text-gray-900"
          >
            {{ hub.name }}
          </h3>
          <span
            v-if="hub.rating != null"
            class="flex shrink-0 items-center gap-1 text-sm font-semibold text-gray-800"
          >
            <UIcon
              name="i-heroicons-star-solid"
              class="h-4 w-4 text-yellow-400"
            />
            {{ hub.rating.toFixed(1) }}
          </span>
        </div>

        <!-- Location -->
        <div class="mt-1.5 flex items-center gap-1 text-sm text-gray-500">
          <UIcon
            name="i-heroicons-map-pin-solid"
            class="h-3.5 w-3.5 shrink-0"
          />
          <span class="truncate">{{ hub.city }}</span>
        </div>

        <!-- Price + CTA -->
        <div class="mt-auto flex items-end justify-between gap-3 pt-4">
          <div class="flex flex-col">
            <span
              class="text-[10px] font-semibold uppercase tracking-widest text-gray-400"
            >
              Starting at
            </span>
            <span
              class="text-3xl font-extrabold leading-tight text-primary-400"
            >
              {{ formattedPrice
              }}<span class="text-base font-bold text-gray-400">/hr</span>
            </span>
          </div>

          <span
            class="rounded-xl bg-primary px-4 py-2 text-sm font-bold text-white"
          >
            BOOK NOW
          </span>
        </div>
      </div>
    </div>
  </NuxtLink>
</template>
