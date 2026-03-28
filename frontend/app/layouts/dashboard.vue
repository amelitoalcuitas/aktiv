<script setup lang="ts">
const sidebarOpen = ref(false);
const scrolled = ref(false);

onMounted(() => {
  const onScroll = () => {
    scrolled.value = window.scrollY > 0;
  };
  window.addEventListener('scroll', onScroll, { passive: true });
  onUnmounted(() => window.removeEventListener('scroll', onScroll));
});
</script>

<template>
  <div class="flex min-h-screen bg-[var(--aktiv-background)]">
    <AppDashboardSidebar v-model:open="sidebarOpen" />
    <div class="flex min-h-screen flex-1 flex-col min-w-0 md:ml-60">
      <!-- Top bar (mobile + desktop) -->
      <header
        :class="[
          'sticky top-0 z-30 bg-[var(--aktiv-surface)] transition-shadow duration-300',
          scrolled ? 'shadow-md' : ''
        ]"
      >
        <div class="flex h-[76px] items-center px-4 md:px-8">
          <!-- Mobile: hamburger + logo -->
          <button
            class="flex items-center justify-center rounded-lg p-1.5 text-[#64748b] hover:bg-[#f0f4f8] md:hidden"
            aria-label="Open sidebar"
            @click="sidebarOpen = true"
          >
            <UIcon name="i-heroicons-bars-3" class="h-5 w-5" />
          </button>
          <span class="ml-3 inline-flex items-center gap-2.5 md:hidden">
            <AppIcon class="h-6 w-auto" />
            <AppLogo class="h-6 w-auto" />
          </span>

          <!-- Spacer -->
          <div class="flex-1" />

          <!-- Right side: notifications + user menu -->
          <nav class="flex items-center gap-2">
            <AppNotificationBell />
            <AppUserMenu />
          </nav>
        </div>
      </header>

      <main class="flex-1 min-w-0 p-6 md:p-8">
        <slot />
      </main>
    </div>
  </div>
</template>
