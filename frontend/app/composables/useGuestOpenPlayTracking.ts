import type { CancelledBy } from '~/types/booking';
import type { HubWebsite } from '~/types/hub';
import { useApi } from '~/utils/api';

export type GuestOpenPlayTrackingStatus =
  | 'pending_payment'
  | 'payment_sent'
  | 'confirmed'
  | 'cancelled';

export interface GuestOpenPlayTrackingParticipant {
  id: string;
  open_play_session_id: string;
  guest_name: string | null;
  guest_email: string | null;
  status: GuestOpenPlayTrackingStatus;
  payment_method: 'pay_on_site' | 'digital_bank';
  price_per_player: string;
  receipt_image_url: string | null;
  receipt_uploaded_at: string | null;
  payment_note: string | null;
  expires_at: string | null;
  cancelled_by: CancelledBy | null;
  joined_at: string | null;
  title: string;
  description: string | null;
  notes: string | null;
  sport: string;
  start_time: string;
  end_time: string;
  court: { id: string; name: string };
  hub: { id: string; username: string | null; name: string; timezone: string; phones: string[]; websites: HubWebsite[] };
}

export function useGuestOpenPlayTracking() {
  const { apiFetch } = useApi();

  async function fetchGuestOpenPlayParticipant(
    token: string
  ): Promise<GuestOpenPlayTrackingParticipant> {
    const response = await apiFetch<{ data: GuestOpenPlayTrackingParticipant }>(
      `/guest-open-play/${token}`
    );
    return response.data;
  }

  async function uploadGuestOpenPlayReceipt(
    token: string,
    file: File
  ): Promise<GuestOpenPlayTrackingParticipant> {
    const formData = new FormData();
    formData.append('receipt_image', file);

    const response = await apiFetch<{ data: GuestOpenPlayTrackingParticipant }>(
      `/guest-open-play/${token}/receipt`,
      {
        method: 'POST',
        body: formData,
      }
    );

    return response.data;
  }

  async function cancelGuestOpenPlayParticipant(
    token: string
  ): Promise<GuestOpenPlayTrackingParticipant> {
    const response = await apiFetch<{ data: GuestOpenPlayTrackingParticipant }>(
      `/guest-open-play/${token}/cancel`,
      {
        method: 'POST',
      }
    );

    return response.data;
  }

  return {
    fetchGuestOpenPlayParticipant,
    uploadGuestOpenPlayReceipt,
    cancelGuestOpenPlayParticipant,
  };
}
