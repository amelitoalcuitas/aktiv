<script setup lang="ts">
defineProps<{
  hidden?: boolean;
  editing?: boolean;
  showEye?: boolean;
}>();

const emit = defineEmits<{
  togglePrivacy: [val: boolean];
}>();
</script>

<template>
  <div class="rounded-lg border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-4">
    <div class="mb-3 flex items-center justify-between gap-2">
      <div class="flex items-center gap-2">
        <h3 class="text-lg font-bold text-[var(--aktiv-ink)]">Leaderboard Stats</h3>
        <UIcon
          v-if="editing || showEye"
          :name="hidden ? 'i-heroicons-eye-slash' : 'i-heroicons-eye'"
          class="h-5 w-5"
          :class="hidden ? 'text-[var(--aktiv-muted)]' : 'text-[var(--aktiv-primary)]'"
        />
      </div>
      <div v-if="editing" class="flex items-center gap-2">
        <span class="text-sm text-[var(--aktiv-muted)]">Visibility</span>
        <USwitch
          :model-value="!hidden"
          @update:model-value="(val) => emit('togglePrivacy', val)"
        />
      </div>
    </div>
    <div class="rounded-md bg-[var(--aktiv-border)]/30 px-4 py-6 text-center">
      <UIcon name="i-heroicons-clock" class="mx-auto mb-2 h-6 w-6 text-[var(--aktiv-muted)] opacity-50" />
      <p class="text-sm text-[var(--aktiv-muted)]">Coming in Phase 5</p>
      <p class="mt-0.5 text-xs text-[var(--aktiv-muted)] opacity-70">
        Your rank, wins, and sport stats will appear here.
      </p>
    </div>
  </div>
</template>
