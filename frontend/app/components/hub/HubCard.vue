<script setup lang="ts">
import type { Hub } from '~/types/hub';

const props = defineProps<{
  hub: Hub;
}>();

const formattedPrice = computed(() =>
  new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    maximumFractionDigits: 0
  }).format(props.hub.lowestPricePerHour)
);
</script>

<template>
  <UCard
    class="overflow-hidden rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] transition duration-150 ease-out hover:cursor-pointer hover:-translate-y-1 hover:scale-[1.02] hover:shadow-xl"
    :ui="{ root: 'ring-0 divide-y-0' }"
  >
    <div class="relative -mx-4 -mt-4 mb-4 h-[200px]">
      <img
        :src="hub.coverImageUrl"
        :alt="hub.name"
        class="h-full w-full rounded-t-lg object-cover"
      />
    </div>

    <div class="p-4">
      <h3 class="m-0 text-2xl font-bold">{{ hub.name }}</h3>
      <p class="mb-0 mt-1 opacity-95">{{ hub.city }}</p>

      <p class="m-0 min-h-12 text-[var(--aktiv-muted)]">
        {{ hub.description }}
      </p>

      <div class="mt-4 flex items-center justify-between gap-3">
        <span
          class="rounded-full px-2.5 py-1 text-sm font-bold"
          :class="
            hub.isOpenNow
              ? 'bg-[#daf7d0] text-[#1e6a0f]'
              : 'bg-[#fee2e2] text-[#9f1239]'
          "
        >
          {{ hub.isOpenNow ? 'Open now' : 'Closed' }}
        </span>
        <span class="text-sm text-[var(--aktiv-muted)]"
          >{{ hub.rating.toFixed(1) }} ({{ hub.reviewsCount }})</span
        >
      </div>

      <div class="mt-4 flex items-center justify-between gap-3">
        <span
          class="rounded-full bg-[var(--aktiv-accent)] px-2.5 py-1 text-sm font-bold"
          >{{ hub.courtsCount }} Courts</span
        >
        <span class="text-sm font-bold text-[var(--aktiv-primary)]"
          >from {{ formattedPrice }}/hr</span
        >
      </div>
    </div>
  </UCard>
</template>
