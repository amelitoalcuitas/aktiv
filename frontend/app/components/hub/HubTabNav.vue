<script setup lang="ts">
defineProps<{
  tabs: { label: string; icon: string; to: string }[];
}>();

const route = useRoute();
const isTabActive = (to: string) => route.path === to;
</script>

<template>
  <nav
    class="sticky top-[76px] z-25 border-b border-[var(--aktiv-border)] bg-[var(--aktiv-surface)]"
  >
    <div class="mx-auto flex w-full max-w-[1400px] items-center px-4 md:px-6">
      <div class="flex min-w-0 flex-1 overflow-x-auto">
        <NuxtLink
          v-for="tab in tabs"
          :key="tab.to"
          :to="tab.to"
          class="inline-flex items-center gap-2 border-b-[3px] px-3 py-4 text-sm font-bold whitespace-nowrap transition"
          :class="
            isTabActive(tab.to)
              ? 'border-[var(--aktiv-primary)] text-[var(--aktiv-primary)] bg-[var(--aktiv-primary)]/8'
              : 'border-transparent text-[var(--aktiv-muted)] hover:text-[var(--aktiv-ink)]'
          "
        >
          <UIcon :name="tab.icon" class="h-4 w-4" />
          {{ tab.label }}
        </NuxtLink>
      </div>
    </div>
  </nav>
</template>
