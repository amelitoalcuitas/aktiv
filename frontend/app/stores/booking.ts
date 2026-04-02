import type { AppNotification } from '~/types/notification';
import type { MyBookingItem } from '~/types/booking';

export const useUserBookingStore = defineStore('userBooking', () => {
  const pendingCount = ref(0);
  const recentBookings = ref<MyBookingItem[]>([]);
  const lastBookingEvent = ref(0);
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const echoChannel = ref<any>(null);

  async function fetchInitial() {
    const { fetchMyBookings } = useBooking();
    const [recent, pending] = await Promise.all([
      fetchMyBookings({ page: 1 }),
      fetchMyBookings({ status: 'pending_payment', page: 1 }),
    ]);
    recentBookings.value = recent.data.slice(0, 5);
    pendingCount.value = pending.meta.total;
  }

  async function refresh() {
    await fetchInitial();
  }

  function subscribe(userId: string) {
    if (echoChannel.value) return;

    const { $echo } = useNuxtApp();
    if (!$echo) return;

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const echoInstance = $echo as any;
    const channel = echoInstance.private(`App.Models.User.${userId}`);

    channel.listen('.notification.new', (payload: AppNotification) => {
      const bookingTypes = [
        'booking_confirmed',
        'booking_rejected',
        'booking_cancelled',
        'booking_cancelled_by_guest',
        'booking_created',
        'receipt_uploaded',
        'open_play_participant_confirmed',
        'open_play_participant_rejected',
        'open_play_participant_cancelled',
        'open_play_session_cancelled',
        'open_play_session_started'
      ];
      if (bookingTypes.includes(payload.activity_type)) {
        refresh();
        lastBookingEvent.value++;
      }
    });

    echoChannel.value = channel;
  }

  function unsubscribe() {
    if (echoChannel.value) {
      echoChannel.value.stopListening('.notification.new');
    }
    echoChannel.value = null;
    pendingCount.value = 0;
    recentBookings.value = [];
  }

  return {
    pendingCount,
    recentBookings,
    lastBookingEvent,
    fetchInitial,
    refresh,
    subscribe,
    unsubscribe,
  };
});
