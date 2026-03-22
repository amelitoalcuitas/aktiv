const USER_FACING_TYPES = ['booking_confirmed', 'booking_rejected', 'booking_cancelled'];

export function useNotificationBooking() {
  async function openBookingFromNotification(bookingId: string, hubId: string, activityType: string) {
    const destination = USER_FACING_TYPES.includes(activityType)
      ? `/bookings?bookingId=${bookingId}`
      : `/dashboard/bookings?hubId=${hubId}&bookingId=${bookingId}`;
    await navigateTo(destination);
  }

  return { openBookingFromNotification };
}
