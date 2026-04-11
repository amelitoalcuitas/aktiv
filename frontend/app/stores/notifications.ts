import type { AppNotification } from '~/types/notification';

interface NotificationsResponse {
  data: AppNotification[];
  next_cursor: string | null;
  has_more: boolean;
}

export const useNotificationStore = defineStore('notifications', () => {
  const items = ref<AppNotification[]>([]);
  const hasMore = ref(false);
  const nextCursor = ref<string | null>(null);
  // Channel reference kept as unknown to avoid type issues with Echo
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const echoChannel = ref<any>(null);

  const unreadCount = computed(
    () => items.value.filter((n) => n.read_at === null).length
  );

  async function fetchInitial() {
    const { apiFetch } = useApi();
    const data = await apiFetch<NotificationsResponse>(
      '/notifications?per_page=50'
    );
    items.value = Array.isArray(data?.data) ? data.data : [];
    hasMore.value = Boolean(data?.has_more);
    nextCursor.value = data?.next_cursor ?? null;
  }

  async function fetchMore() {
    if (!hasMore.value) return;
    const { apiFetch } = useApi();
    const cursor = nextCursor.value
      ? `&cursor=${encodeURIComponent(nextCursor.value)}`
      : '';
    const data = await apiFetch<NotificationsResponse>(
      `/notifications?per_page=20${cursor}`
    );
    items.value.push(...(Array.isArray(data?.data) ? data.data : []));
    hasMore.value = Boolean(data?.has_more);
    nextCursor.value = data?.next_cursor ?? null;
  }

  async function markRead(id: string) {
    const { apiFetch } = useApi();
    const updated = await apiFetch<{ data: AppNotification }>(
      `/notifications/${id}`,
      { method: 'PATCH' }
    );
    const idx = items.value.findIndex((n) => n.id === id);
    if (idx !== -1) items.value[idx] = updated.data;
  }

  async function markAllRead() {
    const { apiFetch } = useApi();
    await apiFetch('/notifications/read-all', { method: 'POST' });
    const now = new Date().toISOString();
    items.value = items.value.map((n) => ({ ...n, read_at: n.read_at ?? now }));
  }

  function prependNotification(notification: AppNotification) {
    if (items.value.some((n) => n.id === notification.id)) return;
    items.value.unshift(notification);
  }

  function subscribe(userId: string) {
    if (echoChannel.value) return;

    const { $echo } = useNuxtApp();
    if (!$echo) return;

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const echoInstance = $echo as any;

    // Reconnect if previously disconnected
    echoInstance.connector.pusher.connection.connect();

    const toast = useToast();
    const { resolveNotificationDestination } = useNotificationBooking();

    const channel = echoInstance.private(`App.Models.User.${userId}`);
    channel.listen('.notification.new', (payload: AppNotification) => {
      prependNotification(payload);
      const t = toast.add({
        title: 'New Notification',
        description: payload.data.message,
        duration: 0,
        orientation: 'horizontal',
        actions: [
          {
            label: 'View',
            color: 'secondary' as const,
            variant: 'outline' as const,
            onClick: () => {
              toast.remove(t.id);
              markRead(payload.id);
              const destination = resolveNotificationDestination({
                activityType: payload.activity_type,
                hubId: payload.data.hub_id,
                itemId: payload.data.item_id,
                bookingId: payload.data.booking_id,
                sessionId: payload.data.session_id
              });
              navigateTo(destination);
            }
          }
        ]
      });
    });
    echoChannel.value = channel;
  }

  function unsubscribe() {
    const { $echo } = useNuxtApp();
    if ($echo) {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const echoInstance = $echo as any;
      if (echoChannel.value) {
        echoInstance.leaveAllChannels();
      }
      echoInstance.connector.pusher.connection.disconnect();
    }
    echoChannel.value = null;
    items.value = [];
    hasMore.value = false;
    nextCursor.value = null;
  }

  return {
    items,
    hasMore,
    nextCursor,
    unreadCount,
    fetchInitial,
    fetchMore,
    markRead,
    markAllRead,
    prependNotification,
    subscribe,
    unsubscribe
  };
});
