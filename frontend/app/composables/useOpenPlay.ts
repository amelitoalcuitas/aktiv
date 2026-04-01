import type { OpenPlayParticipant, OpenPlaySession } from '~/types/openPlay';
import { useApi } from '~/utils/api';

interface JoinSessionPayload {
  payment_method: 'pay_on_site' | 'digital_bank';
  guest_name?: string;
  guest_phone?: string;
  guest_email?: string;
  otp?: string;
}

export function useOpenPlay() {
  const { apiFetch } = useApi();

  async function fetchSessions(hubId: string | number): Promise<OpenPlaySession[]> {
    const res = await apiFetch<{ data: OpenPlaySession[] }>(`/hubs/${hubId}/open-play`);
    return res.data;
  }

  async function sendGuestVerificationCode(
    hubId: string | number,
    sessionId: string,
    email: string
  ): Promise<void> {
    await apiFetch(`/hubs/${hubId}/open-play/${sessionId}/guest-verify`, {
      method: 'POST',
      body: { email }
    });
  }

  async function joinSession(
    hubId: string | number,
    sessionId: string,
    payload: JoinSessionPayload
  ): Promise<OpenPlayParticipant> {
    const res = await apiFetch<{ data: OpenPlayParticipant }>(
      `/hubs/${hubId}/open-play/${sessionId}/join`,
      { method: 'POST', body: payload }
    );

    return res.data;
  }

  async function leaveSession(hubId: string | number, sessionId: string): Promise<void> {
    await apiFetch(`/hubs/${hubId}/open-play/${sessionId}/leave`, {
      method: 'DELETE'
    });
  }

  async function uploadParticipantReceipt(
    hubId: string | number,
    sessionId: string,
    participantId: string,
    file: File,
    token?: string | null
  ): Promise<OpenPlayParticipant> {
    const formData = new FormData();
    formData.append('receipt_image', file);
    if (token) formData.append('token', token);

    const res = await apiFetch<{ data: OpenPlayParticipant }>(
      `/hubs/${hubId}/open-play/${sessionId}/participants/${participantId}/receipt`,
      { method: 'POST', body: formData }
    );

    return res.data;
  }

  return {
    fetchSessions,
    sendGuestVerificationCode,
    joinSession,
    leaveSession,
    uploadParticipantReceipt
  };
}
