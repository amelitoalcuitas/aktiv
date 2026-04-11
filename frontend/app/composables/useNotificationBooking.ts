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
  'open_play_session_started',
  'open_play_session_updated'
];

const OWNER_BOOKING_TYPES: NotificationActivityType[] = [
  'booking_created',
  'receipt_uploaded',
  'booking_cancelled_by_guest'
];

const OWNER_OPEN_PLAY_TYPES: NotificationActivityType[] = [
  'open_play_receipt_uploaded',
  'open_play_participant_joined',
  'open_play_participant_cancelled_by_customer'
];

interface NotificationNavigationTarget {
  activityType: NotificationActivityType;
  hubId?: string;
  itemId?: string;
  bookingId?: string;
  sessionId?: string;
}

function getNotificationDestination({
  activityType,
  hubId,
  itemId,
  bookingId,
  sessionId
}: NotificationNavigationTarget): string {
  if (USER_BOOKING_TYPES.includes(activityType)) {
    const query = bookingId ? `?bookingId=${bookingId}` : '';
    return `/bookings${query}`;
  }

  if (USER_OPEN_PLAY_TYPES.includes(activityType)) {
    const query = itemId ? `?itemId=${itemId}` : '';
    return `/bookings${query}`;
  }

  if (OWNER_OPEN_PLAY_TYPES.includes(activityType) && hubId) {
    const query = sessionId ? `?sessionId=${sessionId}` : '';
    return `/dashboard/hubs/${hubId}/open-play${query}`;
  }

  if (OWNER_BOOKING_TYPES.includes(activityType) && hubId) {
    const query = bookingId ? `?bookingId=${bookingId}` : '';
    return `/dashboard/hubs/${hubId}/bookings${query}`;
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
    activityType: NotificationActivityType,
    sessionId?: string
  ) {
    const destination = resolveNotificationDestination({
      activityType,
      hubId,
      itemId,
      bookingId,
      sessionId
    });

    const route = useRoute();
    const [basePath, queryString = ''] = destination.split('?');
    const params = new URLSearchParams(queryString);
    const targetItemId = params.get('itemId');
    const targetBookingId = params.get('bookingId');
    const targetSessionId = params.get('sessionId');
    const currentItemId = Array.isArray(route.query.itemId)
      ? route.query.itemId[0]
      : route.query.itemId;
    const currentBookingId = Array.isArray(route.query.bookingId)
      ? route.query.bookingId[0]
      : route.query.bookingId;
    const currentSessionId = Array.isArray(route.query.sessionId)
      ? route.query.sessionId[0]
      : route.query.sessionId;
    const isSameUrl =
      route.path === basePath &&
      (currentItemId ?? null) === targetItemId &&
      (currentBookingId ?? null) === targetBookingId &&
      (currentSessionId ?? null) === targetSessionId;

    if (isSameUrl) {
      // Clear the query to re-trigger page watchers when navigating back.
      await navigateTo(basePath, { replace: true });
    }

    await navigateTo(destination);
  }

  return { openBookingFromNotification, resolveNotificationDestination };
}
