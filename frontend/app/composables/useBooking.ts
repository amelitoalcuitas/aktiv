import type { Booking, CalendarBooking, SessionType } from '~/types/booking';

export function useBooking() {
  const { apiFetch } = useApi();

  async function fetchBookings(
    hubId: string | number,
    courtId: string | number
  ): Promise<CalendarBooking[]> {
    const response = await apiFetch<{ data: CalendarBooking[] }>(
      `/hubs/${hubId}/courts/${courtId}/bookings`
    );
    return response.data;
  }

  async function createBooking(
    hubId: string | number,
    courtId: string | number,
    data: {
      sport: string;
      start_time: string;
      end_time: string;
      session_type: SessionType;
    }
  ): Promise<Booking> {
    const response = await apiFetch<{ message: string; data: Booking }>(
      `/hubs/${hubId}/courts/${courtId}/bookings`,
      {
        method: 'POST',
        body: data
      }
    );
    return response.data;
  }

  return { fetchBookings, createBooking };
}
