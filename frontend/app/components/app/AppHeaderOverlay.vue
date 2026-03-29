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
      'inset-x-0 top-0 z-30 transition-all duration-300',
      scrolled ? 'fixed bg-[var(--aktiv-surface)] shadow-md' : 'absolute'
    ]"
  >
    <div
      class="mx-auto flex h-[76px] w-full max-w-[1400px] items-center justify-between px-4 md:px-8"
    >
      <NuxtLink
        to="/"
        class="inline-flex items-center gap-2.5"
        aria-label="Aktiv home"
      >
        <AppIcon class="h-6 w-auto" />
        <AppLogo class="hidden h-6 w-auto sm:block" />
      </NuxtLink>

      <nav class="flex items-center gap-2">
        <UButton
          to="/explore"
          variant="ghost"
          :color="route.path === '/explore' ? 'primary' : 'neutral'"
          :class="
            route.path === '/explore'
              ? 'underline underline-offset-4 font-semibold'
              : ''
          "
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
