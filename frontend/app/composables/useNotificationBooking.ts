import type { NotificationActivityType } from '~/types/notification';

const USER_BOOKING_TYPES: NotificationActivityType[] = [
  'booking_confirmed',
  'booking_rejected',
  'booking_cancelled'
];

const USER_OPEN_PLAY_TYPES: NotificationActivityType[] = [
  'open_play_participant_confirmed',
  'open_play_participant_rejected',
  'open_play_participant_cancelled',
  'open_play_session_cancelled',
  'open_play_session_started'
];

const OWNER_BOOKING_TYPES: NotificationActivityType[] = [
  'booking_created',
  'receipt_uploaded',
  'booking_cancelled_by_guest',
  'open_play_receipt_uploaded'
];

interface NotificationNavigationTarget {
  activityType: NotificationActivityType;
  hubId?: string;
  itemId?: string;
  bookingId?: string;
}

function getNotificationDestination({
  activityType,
  hubId,
  itemId,
  bookingId
}: NotificationNavigationTarget): string {
  if (USER_BOOKING_TYPES.includes(activityType)) {
    const query = bookingId ? `?bookingId=${bookingId}` : '';
    return `/bookings${query}`;
  }

  if (USER_OPEN_PLAY_TYPES.includes(activityType)) {
    const query = itemId ? `?itemId=${itemId}` : '';
    return `/bookings${query}`;
  }

  if (OWNER_BOOKING_TYPES.includes(activityType) && hubId) {
    const query = bookingId && activityType !== 'open_play_receipt_uploaded'
      ? `?bookingId=${bookingId}`
      : '';
    return `/hubs/${hubId}/bookings${query}`;
  }

  return '/notifications';
}

export function useNotificationBooking() {
  function resolveNotificationDestination(target: NotificationNavigationTarget) {
    return getNotificationDestination(target);
  }

  async function openBookingFromNotification(
    itemId: string | undefined,
    bookingId: string | undefined,
    hubId: string | undefined,
    activityType: NotificationActivityType
  ) {
    const destination = resolveNotificationDestination({
      activityType,
      hubId,
      itemId,
      bookingId
    });

    const route = useRoute();
    const [basePath, queryString = ''] = destination.split('?');
    const targetItemId = new URLSearchParams(queryString).get('itemId');
    const targetBookingId = new URLSearchParams(queryString).get('bookingId');
    const currentItemId = Array.isArray(route.query.itemId)
      ? route.query.itemId[0]
      : route.query.itemId;
    const currentBookingId = Array.isArray(route.query.bookingId)
      ? route.query.bookingId[0]
      : route.query.bookingId;
    const isSameUrl =
      route.path === basePath &&
      (currentItemId ?? null) === targetItemId &&
      (currentBookingId ?? null) === targetBookingId;

    if (isSameUrl) {
      // Clear the query to re-trigger page watchers when navigating back.
      await navigateTo(basePath, { replace: true });
    }

    await navigateTo(destination);
  }

  return { openBookingFromNotification, resolveNotificationDestination };
}
