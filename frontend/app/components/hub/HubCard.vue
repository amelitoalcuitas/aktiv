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
  }).format(props.hub.lowestPricePerHour ?? 0)
);
</script>

<template>
  <NuxtLink :to="`/hubs/${hub.id}`" class="block h-full">
    <div
      class="flex h-full flex-col overflow-hidden rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] shadow-sm transition duration-150 ease-out hover:-translate-y-1 hover:scale-[1.02] hover:cursor-pointer hover:shadow-xl"
    >
      <img
        :src="hub.coverImageUrl"
        :alt="hub.name"
        class="h-[210px] w-full shrink-0 object-cover"
      />

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
            <UBadge variant="soft" :color="hub.isOpenNow ? 'success' : 'error'">
              {{ hub.isOpenNow ? 'Open now' : 'Closed' }}
            </UBadge>
            <span class="text-sm text-[var(--aktiv-muted)]">
              {{ (hub.rating ?? 0).toFixed(1) }} ({{ hub.reviewsCount ?? 0 }})
            </span>
          </div>

          <div class="flex items-center justify-between gap-3">
            <UBadge variant="soft" color="secondary">
              {{ hub.courtsCount }} Courts
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
