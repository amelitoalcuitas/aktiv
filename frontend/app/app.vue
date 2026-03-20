<script setup lang="ts">
import { useAuth } from '~/composables/useAuth';
import { useNotificationStore } from '~/stores/notifications';

const { init } = useAuth();
const authStore = useAuthStore();
const notificationStore = useNotificationStore();
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
    } else {
      notificationStore.unsubscribe();
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
