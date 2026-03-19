import type { Booking, CalendarBooking, SessionType } from '~/types/booking';
import { useApi } from '~/utils/api';

interface FetchBookingsParams {
  date_from?: string;
  date_to?: string;
}

export function useBooking() {
  const { apiFetch } = useApi();

  async function fetchHubBookings(
    hubId: string | number,
    params?: FetchBookingsParams
  ): Promise<Record<number, CalendarBooking[]>> {
    const query = new URLSearchParams();
    if (params?.date_from) query.set('date_from', params.date_from);
    if (params?.date_to) query.set('date_to', params.date_to);

    const qs = query.toString();
    const response = await apiFetch<{ data: Record<number, CalendarBooking[]> }>(
      `/hubs/${hubId}/bookings${qs ? `?${qs}` : ''}`
    );
    return response.data;
  }

  async function fetchBookings(
    hubId: string | number,
    courtId: string | number,
    params?: FetchBookingsParams
  ): Promise<CalendarBooking[]> {
    const query = new URLSearchParams();
    if (params?.date_from) query.set('date_from', params.date_from);
    if (params?.date_to) query.set('date_to', params.date_to);

    const qs = query.toString();
    const response = await apiFetch<{ data: CalendarBooking[] }>(
      `/hubs/${hubId}/courts/${courtId}/bookings${qs ? `?${qs}` : ''}`
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

  return { fetchHubBookings, fetchBookings, createBooking, uploadReceipt };
}
