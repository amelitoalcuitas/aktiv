import type { Booking, CalendarBooking, SessionType } from '~/types/booking';
import { useApi } from '~/utils/api';

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

  async function uploadReceipt(
    hubId: string | number,
    courtId: string | number,
    bookingId: number,
    file: File
  ): Promise<{
    id: number;
    status: string;
    receipt_image_url: string;
    receipt_uploaded_at: string;
  }> {
    const formData = new FormData();
    formData.append('receipt_image', file);

    const response = await apiFetch<{
      message: string;
      data: {
        id: number;
        status: string;
        receipt_image_url: string;
        receipt_uploaded_at: string;
      };
    }>(`/hubs/${hubId}/courts/${courtId}/bookings/${bookingId}/receipt`, {
      method: 'POST',
      body: formData
    });
    return response.data;
  }

  return { fetchBookings, createBooking, uploadReceipt };
}
