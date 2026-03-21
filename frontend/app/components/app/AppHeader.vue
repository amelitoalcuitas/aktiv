<script setup lang="ts">
import { useAuth } from '~/composables/useAuth';

const { isAuthenticated } = useAuth();
const route = useRoute();
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
  <header
    :class="[
      'inset-x-0 sticky top-0 z-30 bg-[var(--aktiv-surface)] transition-shadow duration-300',
      scrolled ? 'shadow-md' : ''
    ]"
  >
    <div
      class="mx-auto flex h-[76px] w-full max-w-[1280px] items-center justify-between px-4 md:px-8"
    >
      <!-- Logo -->
      <NuxtLink
        to="/"
        class="inline-flex items-center gap-2.5"
        aria-label="Aktiv home"
      >
        <AppIcon class="h-6 w-auto" />
        <AppLogo class="hidden h-6 w-auto sm:block" />
      </NuxtLink>

      <!-- Nav -->
      <nav class="flex items-center gap-2">
        <UButton
          to="/explore"
          variant="ghost"
          :color="route.path === '/explore' ? 'secondary' : 'neutral'"
          :class="route.path === '/explore' ? 'underline underline-offset-4 font-semibold' : ''"
        >
          Hubs
        </UButton>
        <template v-if="isAuthenticated">
          <AppBookingButton />
          <AppNotificationBell />
          <AppUserMenu variant="header" />
        </template>
        <template v-else>
          <UButton to="/auth/login" color="primary"> Sign In </UButton>
        </template>
      </nav>
    </div>
  </header>
</template>
