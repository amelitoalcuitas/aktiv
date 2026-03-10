<script setup lang="ts">
definePageMeta({ layout: 'hub' });
</script>

<template>
  <UCard
    class="rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-6 shadow-sm"
    :ui="{ root: 'ring-0 divide-y-0' }"
  >
    <div class="flex items-start gap-4">
      <div
        class="rounded-xl bg-[var(--aktiv-border)] p-3 text-[var(--aktiv-primary)]"
      >
        <UIcon name="i-heroicons-trophy" class="h-6 w-6" />
      </div>
      <div>
        <h2 class="m-0 text-2xl font-black text-[var(--aktiv-ink)]">
          Tournaments
        </h2>
        <p class="mt-2 text-[var(--aktiv-muted)]">
          Tournament creation, brackets, and live match updates will be
          implemented in Phase 4.
        </p>
      </div>
    </div>
  </UCard>
</template>
