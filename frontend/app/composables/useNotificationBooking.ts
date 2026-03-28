const USER_FACING_TYPES = [
  'booking_confirmed',
  'booking_rejected',
  'booking_cancelled'
];

export function useNotificationBooking() {
  async function openBookingFromNotification(
    bookingId: string,
    hubId: string,
    activityType: string
  ) {
    const destination = USER_FACING_TYPES.includes(activityType)
      ? `/bookings?bookingId=${bookingId}`
      : `/hubs/${hubId}/bookings?bookingId=${bookingId}`;

    const route = useRoute();
    const isSameUrl =
      route.path ===
        (USER_FACING_TYPES.includes(activityType)
          ? '/bookings'
          : '/hubs/${hubId}/bookings') && route.query.bookingId === bookingId;

    if (isSameUrl) {
      // Already on the same URL — clear bookingId then re-set it to re-trigger the watcher
      const base = USER_FACING_TYPES.includes(activityType)
        ? '/bookings'
        : `/hubs/${hubId}/bookings`;
      await navigateTo(base, { replace: true });
    }

    await navigateTo(destination);
  }

  return { openBookingFromNotification };
}
