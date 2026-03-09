<template>
  <header
    :class="[
      'border-b border-[var(--aktiv-border)]',
      isExplorePage ? 'bg-transparent' : 'bg-[var(--aktiv-surface)]'
    ]"
  >
    <div
      class="mx-auto flex h-[76px] w-full max-w-[1120px] items-center justify-between px-4"
    >
      <NuxtLink
        to="/explore"
        class="inline-flex items-center gap-2.5"
        aria-label="Aktiv home"
      >
        <span
          class="text-xl font-extrabold tracking-tight text-[var(--aktiv-primary)]"
        >
          Aktiv
        </span>
      </NuxtLink>
    </div>
  </header>
</template>

<script setup lang="ts">
const route = useRoute();

const isExplorePage = computed(() => {
  const path = route.path.replace(/\/+$/, '');
  return (
    path === '/explore' ||
    path.endsWith('/explore') ||
    path.includes('/explore/')
  );
});
</script>
