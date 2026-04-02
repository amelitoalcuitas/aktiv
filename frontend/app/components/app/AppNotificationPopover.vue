<script setup lang="ts">
import { useNotificationStore } from '~/stores/notifications';
import { useNotificationBooking } from '~/composables/useNotificationBooking';

const emit = defineEmits<{ close: [] }>();

const notificationStore = useNotificationStore();
const { openBookingFromNotification } = useNotificationBooking();

const popoverItems = computed(() => notificationStore.items.slice(0, 25));
const hasUnread = computed(() => notificationStore.unreadCount > 0);

async function handleToggleRead(id: string) {
  await notificationStore.markRead(id);
}

async function handleMarkAllRead() {
  await notificationStore.markAllRead();
}

async function handleOpenBooking(
  itemId: string | undefined,
  bookingId: string | undefined,
  hubId: string | undefined,
  activityType: import('~/types/notification').NotificationActivityType
) {
  emit('close');
  await openBookingFromNotification(itemId, bookingId, hubId, activityType);
}
</script>

<template>
  <div class="flex w-100 flex-col">
    <!-- Header -->
    <div
      class="flex items-center justify-between border-b border-[#dbe4ef] px-4 py-3"
    >
      <span class="text-sm font-semibold text-[#0f1728]">Notifications</span>
      <button
        v-if="hasUnread"
        class="text-xs text-[#004e89] hover:underline"
        @click="handleMarkAllRead"
      >
        Mark all read
      </button>
    </div>

    <!-- List -->
    <div class="max-h-[420px] overflow-y-auto">
      <template v-if="popoverItems.length > 0">
        <NotificationsNotificationItem
          v-for="item in popoverItems"
          :key="item.id"
          :notification="item"
          :clickable="true"
          @toggle-read="handleToggleRead"
          @open-booking="handleOpenBooking"
        />
      </template>
      <div v-else class="px-4 py-8 text-center text-sm text-[#64748b]">
        No notifications yet.
      </div>
    </div>

    <!-- Footer -->
    <div
      class="border-t border-[#dbe4ef] px-4 py-2.5 text-center text-xs text-[#64748b]"
    >
      <NuxtLink
        to="/notifications"
        class="font-medium text-[#004e89] hover:underline"
        @click="emit('close')"
      >
        More in the notifications page
      </NuxtLink>
    </div>
  </div>
</template>
