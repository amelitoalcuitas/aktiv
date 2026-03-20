import type { Booking, BookingDetail, CalendarBooking, SessionType } from '~/types/booking';
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

  async function sendGuestVerificationCode(
    hubId: string | number,
    courtId: string | number,
    email: string
  ): Promise<void> {
    await apiFetch(`/hubs/${hubId}/courts/${courtId}/guest-verify`, {
      method: 'POST',
      body: { email }
    });
  }

  async function createGuestBooking(
    hubId: string | number,
    courtId: string | number,
    data: {
      email: string;
      otp: string;
      guest_name: string;
      guest_phone?: string;
      sport: string;
      start_time: string;
      end_time: string;
      session_type: SessionType;
    }
  ): Promise<Booking> {
    const response = await apiFetch<{ message: string; data: Booking }>(
      `/hubs/${hubId}/courts/${courtId}/guest-bookings`,
      {
        method: 'POST',
        body: data
      }
    );
    return response.data;
  }

  async function uploadGuestReceipt(
    hubId: string | number,
    courtId: string | number,
    bookingId: number,
    email: string,
    file: File
  ): Promise<{
    id: number;
    status: string;
    receipt_image_url: string;
    receipt_uploaded_at: string;
  }> {
    const formData = new FormData();
    formData.append('receipt_image', file);
    formData.append('email', email);

    const response = await apiFetch<{
      message: string;
      data: {
        id: number;
        status: string;
        receipt_image_url: string;
        receipt_uploaded_at: string;
      };
    }>(`/hubs/${hubId}/courts/${courtId}/guest-bookings/${bookingId}/receipt`, {
      method: 'POST',
      body: formData
    });
    return response.data;
  }

  async function verifyBookingByCode(
    hubId: string | number,
    code: string
  ): Promise<BookingDetail> {
    const response = await apiFetch<{ data: BookingDetail }>(
      `/dashboard/hubs/${hubId}/bookings/verify/${encodeURIComponent(code)}`
    );
    return response.data;
  }

  async function confirmBooking(
    hubId: string | number,
    bookingId: number
  ): Promise<void> {
    await apiFetch(`/dashboard/hubs/${hubId}/bookings/${bookingId}/confirm`, {
      method: 'POST',
    });
  }

  async function rejectBooking(
    hubId: string | number,
    bookingId: number,
    paymentNote: string
  ): Promise<void> {
    await apiFetch(`/dashboard/hubs/${hubId}/bookings/${bookingId}/reject`, {
      method: 'POST',
      body: { payment_note: paymentNote },
    });
  }

  return {
    fetchHubBookings,
    fetchBookings,
    createBooking,
    uploadReceipt,
    sendGuestVerificationCode,
    createGuestBooking,
    uploadGuestReceipt,
    verifyBookingByCode,
    confirmBooking,
    rejectBooking,
  };
}
