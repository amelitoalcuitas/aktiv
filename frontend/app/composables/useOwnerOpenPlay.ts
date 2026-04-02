import type { OpenPlaySession, OpenPlayParticipant } from '~/types/openPlay';
import { useApi } from '~/utils/api';

interface CreateSessionPayload {
  title: string;
  court_id: string;
  start_time: string;
  end_time: string;
  max_players: number;
  price_per_player: number;
  description?: string | null;
  guests_can_join?: boolean;
}

export function useOwnerOpenPlay() {
  const { apiFetch } = useApi();

  async function fetchSessions(hubId: string): Promise<OpenPlaySession[]> {
    const res = await apiFetch<{ data: OpenPlaySession[] }>(
      `/dashboard/hubs/${hubId}/open-play`
    );
    return res.data;
  }

  async function createSession(
    hubId: string,
    data: CreateSessionPayload
  ): Promise<OpenPlaySession> {
    const res = await apiFetch<{ data: OpenPlaySession }>(
      `/dashboard/hubs/${hubId}/open-play`,
      { method: 'POST', body: data }
    );
    return res.data;
  }

  async function fetchSession(
    hubId: string,
    sessionId: string
  ): Promise<OpenPlaySession> {
    const res = await apiFetch<{ data: OpenPlaySession }>(
      `/dashboard/hubs/${hubId}/open-play/${sessionId}`
    );
    return res.data;
  }

  async function updateSession(
    hubId: string,
    sessionId: string,
    data: CreateSessionPayload
  ): Promise<OpenPlaySession> {
    const res = await apiFetch<{ data: OpenPlaySession }>(
      `/dashboard/hubs/${hubId}/open-play/${sessionId}`,
      { method: 'PUT', body: data }
    );
    return res.data;
  }

  async function cancelSession(
    hubId: string,
    sessionId: string
  ): Promise<void> {
    await apiFetch(`/dashboard/hubs/${hubId}/open-play/${sessionId}`, {
      method: 'DELETE',
    });
  }

  async function fetchParticipants(
    hubId: string,
    sessionId: string
  ): Promise<OpenPlayParticipant[]> {
    const res = await apiFetch<{ data: OpenPlayParticipant[] }>(
      `/dashboard/hubs/${hubId}/open-play/${sessionId}/participants`
    );
    return res.data;
  }

  async function confirmParticipant(
    hubId: string,
    sessionId: string,
    participantId: string
  ): Promise<OpenPlayParticipant> {
    const res = await apiFetch<{ data: OpenPlayParticipant }>(
      `/dashboard/hubs/${hubId}/open-play/${sessionId}/participants/${participantId}/confirm`,
      { method: 'POST' }
    );
    return res.data;
  }

  async function rejectParticipant(
    hubId: string,
    sessionId: string,
    participantId: string,
    paymentNote: string
  ): Promise<OpenPlayParticipant> {
    const res = await apiFetch<{ data: OpenPlayParticipant }>(
      `/dashboard/hubs/${hubId}/open-play/${sessionId}/participants/${participantId}/reject`,
      { method: 'POST', body: { payment_note: paymentNote } }
    );
    return res.data;
  }

  async function cancelParticipant(
    hubId: string,
    sessionId: string,
    participantId: string
  ): Promise<void> {
    await apiFetch(
      `/dashboard/hubs/${hubId}/open-play/${sessionId}/participants/${participantId}`,
      { method: 'DELETE' }
    );
  }

  return {
    fetchSessions,
    createSession,
    fetchSession,
    updateSession,
    cancelSession,
    fetchParticipants,
    confirmParticipant,
    rejectParticipant,
    cancelParticipant,
  };
}
