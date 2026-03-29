<script setup lang="ts">
const authStore = useAuthStore();
const { applyRoute, applyCtaLabel } = useHubOwnerRequest();

const footerLinks = computed(() => {
  const links = [
    { label: 'Home', to: '/' },
    { label: 'Explore Hubs', to: '/explore' },
    { label: applyCtaLabel.value, to: applyRoute.value }
  ];

  if (authStore.isAuthenticated) {
    links.push({ label: 'My Bookings', to: '/bookings' });
  }

  return links;
});
</script>

<template>
  <footer class="bg-[#0f1728] text-white">
    <!-- Main grid -->
    <div class="mx-auto max-w-[1280px] px-6 pt-16 pb-12 md:px-10">
      <div class="grid gap-12 sm:grid-cols-2 md:grid-cols-3">
        <!-- Brand -->
        <div>
          <p class="text-xl font-black italic uppercase tracking-wide">
            Aktiv Hub
          </p>
          <p class="mt-4 max-w-[280px] text-sm leading-relaxed text-white/55">
            Find courts, join sessions, and play more — the easiest way to book
            sports venues near you.
          </p>
          <div class="mt-6 flex items-center gap-3">
            <a
              href="https://www.facebook.com/aktivhub/"
              target="_blank"
              rel="noopener noreferrer"
              aria-label="Aktiv on Facebook"
              class="flex h-9 w-9 items-center justify-center rounded-full border border-white/15 text-white/60 transition hover:border-white/40 hover:text-white"
            >
              <UIcon name="i-mdi-facebook" class="h-5 w-5" />
            </a>
          </div>
        </div>

        <!-- Navigate -->
        <div>
          <p
            class="text-xs font-bold uppercase tracking-[0.2em] text-[#4da6e0]"
          >
            Navigate
          </p>
          <nav class="mt-5 flex flex-col gap-3">
            <NuxtLink
              v-for="link in footerLinks"
              :key="link.to"
              :to="link.to"
              class="text-sm text-white/60 transition hover:text-white"
            >
              {{ link.label }}
            </NuxtLink>
          </nav>
        </div>

        <!-- Get Playing -->
        <div>
          <p
            class="text-xs font-bold uppercase tracking-[0.2em] text-[#4da6e0]"
          >
            Get Playing
          </p>
          <p class="mt-5 max-w-[260px] text-sm leading-relaxed text-white/55">
            Book a court today and start competing at the best hubs near you.
          </p>
          <UButton
            to="/explore"
            size="md"
            class="mt-5 rounded-xl border border-white/20 bg-transparent px-5 text-sm font-semibold text-white transition hover:bg-white/10"
            :ui="{ base: 'justify-center' }"
          >
            Explore Hubs →
          </UButton>
        </div>
      </div>
    </div>

    <!-- Bottom bar -->
    <div class="border-t border-white/10">
      <div
        class="mx-auto flex max-w-[1280px] flex-col gap-1 px-6 py-5 md:flex-row md:items-center md:justify-between md:px-10"
      >
        <p class="text-xs text-white/35">© 2026 Aktiv. All Rights Reserved.</p>
        <p class="text-xs text-white/25">
          Find courts. Join sessions. Play more.
        </p>
      </div>
    </div>
  </footer>
</template>
