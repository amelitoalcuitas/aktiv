<script setup lang="ts">
import { useAuth } from '~/composables/useAuth';

const { isAuthenticated } = useAuth();
const authStore = useAuthStore();
const { cancelDeletion } = useSettings();
const toast = useToast();
const route = useRoute();
const scrolled = ref(false);

const pendingDeletion = computed(
  () => authStore.user?.deletion_scheduled_at ?? null
);

function formatDeletionDate(iso: string) {
  return new Date(iso).toLocaleDateString('en-PH', {
    timeZone: 'Asia/Manila',
    month: 'long',
    day: 'numeric',
    year: 'numeric'
  });
}

const cancellingDeletion = ref(false);

async function handleCancelDeletion() {
  cancellingDeletion.value = true;
  try {
    const res = (await cancelDeletion()) as
      | { data?: { deletion_scheduled_at?: string | null } }
      | undefined;
    authStore.user!.deletion_scheduled_at =
      res?.data?.deletion_scheduled_at ?? null;
    toast.add({
      title: 'Account deletion cancelled. Welcome back!',
      color: 'success'
    });
  } catch {
    toast.add({
      title: 'Failed to cancel deletion. Please try again.',
      color: 'error'
    });
  } finally {
    cancellingDeletion.value = false;
  }
}

onMounted(() => {
  const onScroll = () => {
    scrolled.value = window.scrollY > 0;
  };
  window.addEventListener('scroll', onScroll, { passive: true });
  onUnmounted(() => window.removeEventListener('scroll', onScroll));
});
</script>

<template>
  <div
    v-if="pendingDeletion"
    class="flex items-center justify-center gap-3 bg-amber-50 px-4 py-2.5 text-sm text-amber-800"
  >
    <UIcon
      name="i-heroicons-exclamation-triangle"
      class="h-4 w-4 flex-shrink-0"
    />
    <span>
      Your account is scheduled for deletion on
      <strong>{{ formatDeletionDate(pendingDeletion) }}</strong
      >.
    </span>
    <UButton
      size="xs"
      color="warning"
      variant="soft"
      :loading="cancellingDeletion"
      @click="handleCancelDeletion"
    >
      Cancel deletion
    </UButton>
  </div>
  <header
    :class="[
      'inset-x-0 sticky top-0 z-30 bg-[var(--aktiv-surface)] transition-shadow duration-300',
      scrolled ? 'shadow-md' : ''
    ]"
  >
    <div
      class="mx-auto flex h-[76px] w-full max-w-[1400px] items-center justify-between px-4 md:px-8"
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
      <nav class="flex items-center gap-1">
        <UButton
          to="/explore"
          variant="ghost"
          :color="route.path === '/explore' ? 'primary' : 'neutral'"
          :class="[
            'h-11 rounded-none border-b-4 border-transparent px-4',
            route.path === '/explore'
              ? 'border-[#0f76bf] bg-[#e8f0f8] font-semibold'
              : 'hover:bg-[#f0f4f8]'
          ]"
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
