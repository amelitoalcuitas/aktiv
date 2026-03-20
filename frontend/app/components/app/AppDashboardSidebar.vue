<script setup lang="ts">
import { useAuth } from '~/composables/useAuth';
import { useHubStore } from '~/stores/hub';

const { user, logout } = useAuth();
const route = useRoute();
const hubStore = useHubStore();

const isVerifyModalOpen = ref(false);

const verifyHub = computed(() => hubStore.myHubs[0] ?? null);

const navLinks = [
  { label: 'My Hubs', icon: 'i-heroicons-building-office-2', to: '/dashboard' },
  { label: 'Courts', icon: 'i-heroicons-squares-2x2', to: '/dashboard/courts' },
  {
    label: 'Bookings',
    icon: 'i-heroicons-calendar-days',
    to: '/dashboard/bookings'
  },
  {
    label: 'Settings',
    icon: 'i-heroicons-cog-6-tooth',
    to: '/dashboard/settings'
  }
];

const isActive = (to: string) => {
  if (to === '/dashboard') return route.path === '/dashboard';
  return route.path.startsWith(to);
};
</script>

<template>
  <aside
    class="fixed inset-y-0 left-0 z-30 flex w-60 flex-col border-r border-[#dbe4ef] bg-white"
  >
    <!-- Logo -->
    <div class="flex h-16 items-center border-b border-[#dbe4ef] px-6">
      <NuxtLink
        to="/"
        class="text-xl font-extrabold tracking-tight text-[#004e89]"
      >
        Aktiv
      </NuxtLink>
      <span
        class="ml-2 rounded-md bg-[#e8f0f8] px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#004e89]"
      >
        Dashboard
      </span>
    </div>

    <!-- Verify Booking button -->
    <div class="border-b border-[#dbe4ef] px-3 py-3">
      <button
        class="flex w-full items-center gap-3 rounded-xl bg-[#004e89] px-3 py-2.5 text-sm font-semibold text-white transition hover:bg-[#003d6b]"
        @click="isVerifyModalOpen = true"
      >
        <UIcon name="i-heroicons-qr-code" class="h-5 w-5 flex-shrink-0" />
        Verify Booking
      </button>
    </div>

    <!-- Nav -->
    <nav class="flex-1 overflow-y-auto px-3 py-4">
      <ul class="space-y-0.5">
        <li v-for="link in navLinks" :key="link.to">
          <NuxtLink
            :to="link.to"
            :class="[
              'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
              isActive(link.to)
                ? 'bg-[#e8f0f8] text-[#004e89]'
                : 'text-[#3a4a5c] hover:bg-[#f0f4f8] hover:text-[#004e89]'
            ]"
          >
            <UIcon :name="link.icon" class="h-5 w-5 flex-shrink-0" />
            {{ link.label }}
          </NuxtLink>
        </li>
      </ul>
    </nav>

    <!-- User footer -->
    <div class="border-t border-[#dbe4ef] p-4">
      <div class="flex items-center gap-3">
        <UAvatar
          :src="user?.avatar_url ?? undefined"
          :alt="user?.name"
          icon="i-heroicons-user"
          size="sm"
          class="flex-shrink-0"
        />
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-semibold text-[#0f1728]">
            {{ user?.name }}
          </p>
          <p class="truncate text-xs text-[#64748b]">{{ user?.email }}</p>
        </div>
        <UButton
          icon="i-heroicons-arrow-right-on-rectangle"
          color="neutral"
          variant="ghost"
          size="sm"
          aria-label="Logout"
          @click="logout"
        />
      </div>
    </div>
  </aside>

  <BookingVerifyModal
    v-model:open="isVerifyModalOpen"
    :hub="verifyHub"
  />
</template>
