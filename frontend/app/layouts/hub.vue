<script setup lang="ts">
// Hub-specific header logic lives in HubProfileHeader component
</script>

<template>
  <div
    class="flex min-h-screen flex-col bg-[var(--aktiv-background)] text-[var(--aktiv-ink)]"
  >
    <AppHeader />
    <HubProfileHeader />
    <main class="mx-auto w-full max-w-[1400px] grow px-3 py-4 md:px-6 md:py-10">
      <slot />
    </main>
    <AppFooter />
  </div>
</template>
