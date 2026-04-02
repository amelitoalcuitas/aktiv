import type { HubEvent } from '~/types/hub';
import { useApi } from '~/utils/api';

interface StoreEventPayload {
  title?: string | null
  description?: string | null
  event_type: 'closure' | 'promo' | 'announcement' | 'voucher'
  date_from: string
  date_to: string
  time_from?: string | null
  time_to?: string | null
  discount_type?: 'percent' | 'flat' | null
  discount_value?: number | null
  voucher_code?: string | null
  show_announcement?: boolean
  limit_total_uses?: boolean
  max_total_uses?: number | null
  limit_per_user_uses?: boolean
  max_uses_per_user?: number | null
  affected_courts?: string[] | null
  court_discounts?: Array<{
    court_id: string
    discount_type: 'percent' | 'flat'
    discount_value: number
  }> | null
  is_active?: boolean
}

export function useHubEvents() {
  const { apiFetch } = useApi();

  async function fetchEvents(
    hubId: string,
    params?: { date_from?: string; date_to?: string }
  ): Promise<HubEvent[]> {
    const res = await apiFetch<{ data: HubEvent[] }>(
      `/dashboard/hubs/${hubId}/events`,
      { query: params }
    );
    return res.data;
  }

  async function createEvent(hubId: string, payload: StoreEventPayload): Promise<HubEvent> {
    const res = await apiFetch<{ data: HubEvent }>(`/dashboard/hubs/${hubId}/events`, {
      method: 'POST',
      body: payload,
    });
    return res.data;
  }

  async function fetchEvent(hubId: string, eventId: string): Promise<HubEvent> {
    const res = await apiFetch<{ data: HubEvent }>(
      `/dashboard/hubs/${hubId}/events/${eventId}`
    );
    return res.data;
  }

  async function updateEvent(hubId: string, eventId: string, payload: Partial<StoreEventPayload>): Promise<HubEvent> {
    const res = await apiFetch<{ data: HubEvent }>(`/dashboard/hubs/${hubId}/events/${eventId}`, {
      method: 'PUT',
      body: payload,
    });
    return res.data;
  }

  async function deleteEvent(hubId: string, eventId: string): Promise<void> {
    await apiFetch(`/dashboard/hubs/${hubId}/events/${eventId}`, { method: 'DELETE' });
  }

  async function toggleEvent(hubId: string, eventId: string): Promise<HubEvent> {
    const res = await apiFetch<{ data: HubEvent }>(`/dashboard/hubs/${hubId}/events/${eventId}/toggle`, {
      method: 'PATCH',
    });
    return res.data;
  }

  return { fetchEvents, fetchEvent, createEvent, updateEvent, deleteEvent, toggleEvent };
}
