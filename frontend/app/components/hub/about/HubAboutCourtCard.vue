<script setup lang="ts">
import type { Court } from '~/types/hub';

const props = defineProps<{
  court: Court;
}>();

const emit = defineEmits<{
  (e: 'book-court', court: Court): void;
}>();

const priceLabel = computed(() =>
  parseFloat(props.court.price_per_hour).toLocaleString('en-PH')
);
</script>

<template>
  <div
    class="flex flex-col overflow-hidden rounded-xl border border-[var(--aktiv-border)]"
  >
    <div
      class="relative h-[140px] w-full shrink-0 overflow-hidden bg-[var(--aktiv-border)]"
    >
      <AppImageViewer
        v-if="court.image_url"
        :src="court.image_url"
        :alt="court.name"
        wrapper-class="h-full w-full cursor-pointer"
        image-class="h-full w-full object-cover transition-transform duration-300 ease-out hover:scale-105"
      />
      <div
        v-else
        class="flex h-full w-full flex-col items-center justify-center gap-2 text-[var(--aktiv-muted)]"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="h-10 w-10 opacity-40"
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

    <div class="flex flex-1 flex-col gap-2 p-3">
      <div class="flex min-w-0 items-center justify-between gap-2">
        <p
          class="min-w-0 truncate font-semibold text-[var(--aktiv-ink)]"
          :title="court.name"
        >
          {{ court.name }}
        </p>
        <span class="text-xl font-bold text-[var(--aktiv-primary)]">
          ₱{{ priceLabel }}<span class="text-sm font-normal text-[var(--aktiv-muted)]">
            /hr
          </span>
        </span>
      </div>

      <div
        class="flex flex-wrap gap-x-3 gap-y-1 text-sm text-[var(--aktiv-muted)]"
      >
        <span class="inline-flex items-center gap-1">
          <UIcon
            :name="
              court.indoor
                ? 'i-heroicons-building-office-2'
                : 'i-heroicons-sun'
            "
            class="h-4 w-4 shrink-0"
          />
          {{ court.indoor ? 'Indoor' : 'Outdoor' }}
        </span>
        <span
          v-if="court.surface"
          class="inline-flex items-center gap-1 capitalize"
        >
          <UIcon name="i-heroicons-squares-2x2" class="h-4 w-4 shrink-0" />
          {{ court.surface }}
        </span>
      </div>

      <div v-if="court.sports.length > 0" class="flex flex-wrap gap-1">
        <UBadge
          v-for="sport in court.sports"
          :key="sport"
          :label="sport"
          variant="subtle"
          color="neutral"
          class="capitalize text-xs"
        />
      </div>

      <UButton
        block
        size="xs"
        variant="ghost"
        color="primary"
        label="Book this Court"
        icon="i-heroicons-calendar-days"
        class="mt-auto"
        @click="emit('book-court', court)"
      />
    </div>
  </div>
</template>
