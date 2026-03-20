<script setup lang="ts">
import { useNotificationStore } from '~/stores/notifications';

const notificationStore = useNotificationStore();
const isOpen = ref(false);
</script>

<template>
  <div class="relative">
    <UPopover
      v-model:open="isOpen"
      :content="{
        align: 'end',
        side: 'bottom',
        sideOffset: 8
      }"
    >
      <button
        class="relative flex h-9 w-9 items-center justify-center rounded-full text-[#3a4a5c] transition hover:bg-[#f0f4f8] hover:text-[#004e89]"
        aria-label="Notifications"
      >
        <UIcon name="i-heroicons-bell" class="h-5 w-5" />
        <span
          v-if="notificationStore.unreadCount > 0"
          class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-red-500"
        />
      </button>

      <template #content>
        <AppNotificationPopover @close="isOpen = false" />
      </template>
    </UPopover>
  </div>
</template>
