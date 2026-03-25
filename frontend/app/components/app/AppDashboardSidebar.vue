<script setup lang="ts">
import { useHubStore } from '~/stores/hub';

const props = defineProps<{ open?: boolean }>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const route = useRoute();
const hubStore = useHubStore();

const isVerifyModalOpen = ref(false);

const verifyHub = computed(() => hubStore.myHubs[0] ?? null);

const navLinks = [
  { label: 'Overview', icon: 'i-heroicons-home', to: '/dashboard' },
  { label: 'My Hubs', icon: 'i-heroicons-building-office-2', to: '/dashboard/hubs' },
  { label: 'Courts', icon: 'i-heroicons-squares-2x2', to: '/dashboard/courts' },
  {
    label: 'Bookings',
    icon: 'i-heroicons-calendar-days',
    to: '/dashboard/bookings'
  },
  {
    label: 'Events',
    icon: 'i-heroicons-megaphone',
    to: '/dashboard/events'
  },
  {
    label: 'Reviews',
    icon: 'i-heroicons-star',
    to: '/dashboard/reviews'
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

const close = () => emit('update:open', false);

// Close sidebar on route change (mobile)
watch(() => route.path, close);
</script>

<template>
  <!-- Overlay (mobile only) -->
  <Transition name="fade">
    <div
      v-if="open"
      class="fixed inset-0 z-40 bg-black/40 md:hidden"
      @click="close"
    />
  </Transition>

  <aside
    :class="[
      'fixed inset-y-0 left-0 z-50 flex w-60 flex-col border-r border-[#dbe4ef] bg-white transition-transform duration-300',
      open ? 'translate-x-0' : '-translate-x-full md:translate-x-0'
    ]"
  >
    <!-- Logo -->
    <div class="flex h-16 items-center border-b border-[#dbe4ef] px-6">
      <NuxtLink to="/" class="inline-flex items-center gap-2">
        <AppIcon class="h-5 w-auto" />
        <AppLogo class="h-5 w-auto" />
      </NuxtLink>
      <!-- Close button (mobile only) -->
      <button
        class="ml-auto rounded-lg p-1 text-[#64748b] hover:bg-[#f0f4f8] md:hidden"
        aria-label="Close sidebar"
        @click="close"
      >
        <UIcon name="i-heroicons-x-mark" class="h-5 w-5" />
      </button>
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

  </aside>

  <BookingVerifyModal
    v-model:open="isVerifyModalOpen"
    :hub="verifyHub"
  />
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
