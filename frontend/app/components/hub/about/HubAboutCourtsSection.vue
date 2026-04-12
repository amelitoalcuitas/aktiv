<script setup lang="ts">
import type { Court } from '~/types/hub';

defineProps<{
  courts: Court[];
}>();

const emit = defineEmits<{
  (e: 'book-court', court: Court): void;
}>();
</script>

<template>
  <div class="rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-4 md:p-6">
    <h2 class="text-lg font-bold text-[var(--aktiv-ink)]">Courts</h2>
    <div
      v-if="courts.length > 0"
      class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2"
    >
      <HubAboutCourtCard
        v-for="court in courts"
        :key="court.id"
        :court="court"
        @book-court="emit('book-court', $event)"
      />
    </div>
    <p v-else class="mt-1 text-sm text-[var(--aktiv-muted)]">
      No courts listed.
    </p>
  </div>
</template>
