<script setup lang="ts">
const authStore = useAuthStore();
const { applyRoute, applyCtaLabel } = useHubOwnerRequest();

const footerLinks = computed(() => {
  const links = [
    { label: 'Home', to: '/' },
    { label: 'Hubs', to: '/explore' },
    { label: applyCtaLabel.value, to: applyRoute.value }
  ];

  if (authStore.isAuthenticated) {
    links.push({ label: 'My Bookings', to: '/bookings' });
  }

  return links;
});
</script>

<template>
  <footer
    class="border-t border-[var(--aktiv-primary)] bg-[var(--aktiv-primary)]"
  >
    <div
      class="mx-auto flex w-full max-w-[1120px] flex-col gap-5 px-4 py-6 text-white md:flex-row md:items-center md:justify-between"
    >
      <div>
        <p class="m-0 text-base font-semibold text-white">Aktiv © 2026</p>
        <small class="mt-1 block text-sm text-white/80">
          Find courts. Join sessions. Play more.
        </small>
      </div>

      <nav class="flex flex-wrap gap-x-5 gap-y-2 text-sm text-white/90">
        <NuxtLink
          v-for="link in footerLinks"
          :key="link.to"
          :to="link.to"
          class="transition hover:text-white hover:underline"
        >
          {{ link.label }}
        </NuxtLink>
      </nav>
    </div>
  </footer>
</template>
