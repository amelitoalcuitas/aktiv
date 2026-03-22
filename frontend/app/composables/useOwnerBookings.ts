import type { BookingDetail, BookingStatus } from '~/types/booking';
import { useApi } from '~/utils/api';

interface FetchBookingsParams {
  status?: BookingStatus;
  court_id?: string;
  date_from?: string;
  date_to?: string;
}

interface UserSearchResult {
  id: string;
  name: string;
  email: string;
  phone: string | null;
  avatar_url: string | null;
}

interface WalkInPayload {
  court_id: string;
  sport?: string;
  start_time: string;
  end_time: string;
  session_type?: 'private' | 'open_play';
  booked_by?: string | null;
  guest_name?: string | null;
  guest_phone?: string | null;
  guest_email?: string | null;
}

export function useOwnerBookings() {
  const { apiFetch } = useApi();

  async function fetchHubBookings(
    hubId: number | string,
    params?: FetchBookingsParams
  ): Promise<BookingDetail[]> {
    const query = new URLSearchParams();
    if (params?.status) query.set('status', params.status);
    if (params?.court_id) query.set('court_id', String(params.court_id));
    if (params?.date_from) query.set('date_from', params.date_from);
    if (params?.date_to) query.set('date_to', params.date_to);

    const qs = query.toString();
    const res = await apiFetch<{ data: BookingDetail[] }>(
      `/dashboard/hubs/${hubId}/bookings${qs ? `?${qs}` : ''}`
    );
    return res.data;
  }

  async function confirmBooking(
    hubId: number | string,
    bookingId: string
  ): Promise<BookingDetail> {
    const res = await apiFetch<{ message: string; data: BookingDetail }>(
      `/dashboard/hubs/${hubId}/bookings/${bookingId}/confirm`,
      { method: 'POST' }
    );
    return res.data;
  }

  async function rejectBooking(
    hubId: number | string,
    bookingId: string,
    paymentNote: string
  ): Promise<BookingDetail> {
    const res = await apiFetch<{ message: string; data: BookingDetail }>(
      `/dashboard/hubs/${hubId}/bookings/${bookingId}/reject`,
      { method: 'POST', body: { payment_note: paymentNote } }
    );
    return res.data;
  }

  async function cancelBooking(
    hubId: number | string,
    bookingId: string
  ): Promise<BookingDetail> {
    const res = await apiFetch<{ message: string; data: BookingDetail }>(
      `/dashboard/hubs/${hubId}/bookings/${bookingId}/cancel`,
      { method: 'POST' }
    );
    return res.data;
  }

  async function createWalkIn(
    hubId: number | string,
    courtId: number | string,
    data: WalkInPayload
  ): Promise<BookingDetail> {
    const res = await apiFetch<{ message: string; data: BookingDetail }>(
      `/dashboard/hubs/${hubId}/courts/${courtId}/walk-in`,
      { method: 'POST', body: data }
    );
    return res.data;
  }

  async function searchUsers(query: string): Promise<UserSearchResult[]> {
    const res = await apiFetch<{ data: UserSearchResult[] }>(
      `/dashboard/users/search?q=${encodeURIComponent(query)}`
    );
    return res.data;
  }

  async function updateBooking(
    hubId: number | string,
    bookingId: string,
    data: Record<string, any>
  ): Promise<BookingDetail> {
    const res = await apiFetch<{ message: string; data: BookingDetail }>(
      `/dashboard/hubs/${hubId}/bookings/${bookingId}`,
      { method: 'PUT', body: data }
    );
    return res.data;
  }

  return {
    fetchHubBookings,
    confirmBooking,
    rejectBooking,
    cancelBooking,
    createWalkIn,
    updateBooking,
    searchUsers
  };
}
