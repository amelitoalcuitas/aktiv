<script setup lang="ts">
import { useNotificationStore } from '~/stores/notifications';
import { useNotificationBooking } from '~/composables/useNotificationBooking';

definePageMeta({ middleware: ['auth'] });

const notificationStore = useNotificationStore();
const { openBookingFromNotification } = useNotificationBooking();
const toast = useToast();

const loading = ref(false);
const loadingMore = ref(false);

// Group notifications by date label
const groupedNotifications = computed(() => {
  const groups: Record<string, typeof notificationStore.items> = {};
  const today = new Date();
  const yesterday = new Date(today);
  yesterday.setDate(yesterday.getDate() - 1);

  for (const notification of notificationStore.items) {
    const date = new Date(notification.created_at);
    let label: string;

    if (isSameDay(date, today)) {
      label = 'Today';
    } else if (isSameDay(date, yesterday)) {
      label = 'Yesterday';
    } else {
      label = date.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
    }

    if (!groups[label]) groups[label] = [];
    groups[label]!.push(notification);
  }

  return groups;
});

function isSameDay(a: Date, b: Date): boolean {
  return a.getFullYear() === b.getFullYear()
    && a.getMonth() === b.getMonth()
    && a.getDate() === b.getDate();
}

// Load on mount (store may already have data from the popover; only fetch if empty)
onMounted(async () => {
  if (notificationStore.items.length === 0) {
    loading.value = true;
    try {
      await notificationStore.fetchInitial();
    } finally {
      loading.value = false;
    }
  }

  // Set up infinite scroll
  window.addEventListener('scroll', handleScroll, { passive: true });
});

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll);
});

async function handleScroll() {
  if (loadingMore.value || !notificationStore.hasMore) return;
  const scrollBottom = window.scrollY + window.innerHeight;
  const docHeight = document.documentElement.scrollHeight;
  if (docHeight - scrollBottom < 300) {
    loadingMore.value = true;
    try {
      await notificationStore.fetchMore();
    } finally {
      loadingMore.value = false;
    }
  }
}

async function handleToggleRead(id: string) {
  await notificationStore.markRead(id);
}

async function handleMarkAllRead() {
  await notificationStore.markAllRead();
  toast.add({ title: 'All notifications marked as read.', color: 'success' });
}

async function handleOpenBooking(
  itemId: string | undefined,
  bookingId: string | undefined,
  hubId: string | undefined,
  activityType: import('~/types/notification').NotificationActivityType
) {
  await openBookingFromNotification(itemId, bookingId, hubId, activityType);
}
</script>

<template>
  <div class="mx-auto flex min-h-full max-w-[700px] flex-col px-4 py-8 md:px-8">
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-xl font-semibold text-[#0f1728]">Notifications</h1>
      <UButton
        v-if="notificationStore.unreadCount > 0"
        variant="ghost"
        size="sm"
        color="primary"
        @click="handleMarkAllRead"
      >
        Mark all as read
      </UButton>
    </div>

    <!-- Content -->
    <div class="flex-1">
    <!-- Loading skeleton -->
    <div v-if="loading" class="space-y-2">
      <div v-for="i in 5" :key="i" class="h-16 animate-pulse rounded-xl bg-[#f0f4f8]" />
    </div>

    <!-- Empty state -->
    <div
      v-else-if="Object.keys(groupedNotifications).length === 0"
      class="flex flex-col items-center justify-center py-16 text-center"
    >
      <UIcon name="i-heroicons-bell-slash" class="mb-3 h-10 w-10 text-[#94a3b8]" />
      <p class="text-sm text-[#64748b]">You're all caught up. No notifications yet.</p>
    </div>

    <!-- Grouped list -->
    <div v-else class="space-y-6">
      <div v-for="(group, label) in groupedNotifications" :key="label">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-[#94a3b8]">
          {{ label }}
        </p>
        <div class="overflow-hidden rounded-xl border border-[#dbe4ef] bg-white">
          <NotificationsNotificationItem
            v-for="(item, idx) in group"
            :key="item.id"
            :notification="item"
            :clickable="true"
            :class="idx < group.length - 1 ? 'border-b border-[#dbe4ef]' : ''"
            @toggle-read="handleToggleRead"
            @open-booking="handleOpenBooking"
          />
        </div>
      </div>
    </div>
    </div>

    <!-- Load more indicator -->
    <div v-if="loadingMore" class="mt-4 flex justify-center">
      <UIcon name="i-heroicons-arrow-path" class="h-5 w-5 animate-spin text-[#64748b]" />
    </div>
  </div>
</template>
