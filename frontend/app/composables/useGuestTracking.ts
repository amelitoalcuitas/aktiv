import type { BookingStatus } from '~/types/booking';
import { useApi } from '~/utils/api';

export interface GuestTrackingBooking {
  id: string;
  booking_code: string;
  status: BookingStatus;
  guest_name: string | null;
  start_time: string;
  end_time: string;
  expires_at: string | null;
  total_price: string | null;
  payment_method: string | null;
  receipt_image_url: string | null;
  receipt_uploaded_at: string | null;
  payment_note: string | null;
  court: { id: string; name: string };
  hub: { id: string; name: string; slug: string };
}

export function useGuestTracking() {
  const { apiFetch } = useApi();

  async function fetchGuestBooking(token: string): Promise<GuestTrackingBooking> {
    const response = await apiFetch<{ data: GuestTrackingBooking }>(`/guest-bookings/${token}`);
    return response.data;
  }

  async function uploadGuestTrackingReceipt(
    token: string,
    file: File
  ): Promise<GuestTrackingBooking> {
    const formData = new FormData();
    formData.append('receipt_image', file);
    const response = await apiFetch<{ data: GuestTrackingBooking }>(`/guest-bookings/${token}/receipt`, {
      method: 'POST',
      body: formData,
    });
    return response.data;
  }

  async function cancelGuestBooking(token: string): Promise<GuestTrackingBooking> {
    const response = await apiFetch<{ data: GuestTrackingBooking }>(`/guest-bookings/${token}/cancel`, {
      method: 'POST',
    });
    return response.data;
  }

  return { fetchGuestBooking, uploadGuestTrackingReceipt, cancelGuestBooking };
}
