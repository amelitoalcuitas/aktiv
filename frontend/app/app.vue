<script setup lang="ts">
import { useAuth } from '~/composables/useAuth';
import { useNotificationStore } from '~/stores/notifications';
import { useUserBookingStore } from '~/stores/booking';

const { init } = useAuth();
const authStore = useAuthStore();
const notificationStore = useNotificationStore();
const userBookingStore = useUserBookingStore();
const toaster = { position: 'bottom-left' };

// Rehydrate auth state from cookie on every page load
await init();

// Subscribe to WebSocket notifications when authenticated
watch(
  () => authStore.user,
  (user) => {
    if (user) {
      notificationStore.fetchInitial();
      notificationStore.subscribe(user.id);
      userBookingStore.fetchInitial();
      userBookingStore.subscribe(user.id);
    } else {
      notificationStore.unsubscribe();
      userBookingStore.unsubscribe();
    }
  },
  { immediate: true }
);
</script>

<template>
  <UApp :toaster="toaster">
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
  </UApp>
</template>
