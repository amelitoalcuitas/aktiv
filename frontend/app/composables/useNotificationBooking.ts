export function useNotificationBooking() {
  async function openBookingFromNotification(bookingId: number, hubId: number) {
    await navigateTo(`/dashboard/bookings?hubId=${hubId}&bookingId=${bookingId}`);
  }

  return { openBookingFromNotification };
}
