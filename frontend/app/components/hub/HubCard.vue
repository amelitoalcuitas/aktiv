<script setup lang="ts">
import type { Hub } from '~/types/hub';
import { isHubOpenNow } from '~/composables/useHubs';

const props = defineProps<{
  hub: Hub;
}>();

const isOpen = computed(() => isHubOpenNow(props.hub));
const hasOperatingHours = computed(() => !!props.hub.operating_hours?.length);

const imgSrc = ref(
  props.hub.cover_image_url ?? props.hub.coverImageUrl ?? ''
);

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
      class="flex h-full flex-col overflow-hidden rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] shadow-sm transition duration-150 ease-out hover:-translate-y-1 hover:scale-[1.02] hover:cursor-pointer hover:shadow-xl"
    >
      <!-- Cover image or placeholder -->
      <div
        class="relative h-[210px] w-full shrink-0 overflow-hidden bg-[var(--aktiv-border)]"
      >
        <img
          v-if="imgSrc"
          :src="imgSrc"
          :alt="hub.name"
          class="h-full w-full object-cover"
          @error="onImgError"
        />
        <div
          v-else
          class="flex h-full w-full flex-col items-center justify-center gap-2 text-[var(--aktiv-muted)]"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-12 w-12 opacity-40"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="1"
          >
            <rect x="3" y="3" width="18" height="18" rx="2" />
            <circle cx="8.5" cy="8.5" r="1.5" />
            <path d="M21 15l-5-5L5 21" />
          </svg>
          <span class="text-xs font-medium opacity-50">No photo</span>
        </div>
      </div>

      <div class="flex flex-1 flex-col p-4">
        <h3 class="m-0 line-clamp-2 text-xl font-bold text-[var(--aktiv-ink)]">
          {{ hub.name }}
        </h3>
        <p class="mt-1 text-sm text-[var(--aktiv-muted)]">{{ hub.city }}</p>
        <p class="mt-2 line-clamp-2 text-sm text-[var(--aktiv-muted)]">
          {{ hub.description }}
        </p>

        <div class="mt-auto flex flex-col gap-3 pt-4">
          <div class="flex items-center justify-between gap-3">
            <UBadge v-if="hasOperatingHours" variant="soft" :color="isOpen ? 'success' : 'error'">
              {{ isOpen ? 'Open now' : 'Closed' }}
            </UBadge>
            <span v-else />
            <span class="text-sm text-[var(--aktiv-muted)]">
              {{ (hub.rating ?? 0).toFixed(1) }} ({{ hub.reviewsCount ?? 0 }})
            </span>
          </div>

          <div class="flex items-center justify-between gap-3">
            <UBadge variant="soft" color="secondary">
              {{ courtsCount }} Courts
            </UBadge>
            <span class="text-sm font-bold text-[var(--aktiv-primary)]">
              from {{ formattedPrice }}/hr
            </span>
          </div>
        </div>
      </div>
    </div>
  </NuxtLink>
</template>
