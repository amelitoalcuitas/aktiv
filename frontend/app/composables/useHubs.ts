import type { Hub, Court } from '~/types/hub';
import { useApi } from '~/utils/api';

export function useHubs() {
  const { apiFetch } = useApi();

  // ── Hubs ──────────────────────────────────────────────────────────────────

  async function fetchHubs(): Promise<Hub[]> {
    const res = await apiFetch<{ data: Hub[] }>('/hubs');
    return res.data;
  }

  async function fetchHub(id: number | string): Promise<Hub> {
    const res = await apiFetch<{ data: Hub }>(`/hubs/${id}`);
    return res.data;
  }

  async function fetchMyHubs(): Promise<Hub[]> {
    const res = await apiFetch<{ data: Hub[] }>('/dashboard/hubs');
    return res.data;
  }

  async function createHub(payload: {
    name: string;
    description?: string;
    city: string;
    zip_code: string;
    province: string;
    country: string;
    address: string;
    address_line2?: string | null;
    landmark?: string | null;
    lat?: number | null;
    lng?: number | null;
    cover_image_url?: string | null;
    sports?: string[];
  }): Promise<Hub> {
    const res = await apiFetch<{ data: Hub }>('/hubs', {
      method: 'POST',
      body: payload
    });
    return res.data;
  }

  async function updateHub(
    id: number | string,
    payload: Partial<{
      name: string;
      description: string;
      city: string;
      zip_code: string;
      province: string;
      country: string;
      address: string;
      address_line2: string | null;
      landmark: string | null;
      lat: number | null;
      lng: number | null;
      cover_image_url: string | null;
      sports: string[];
    }>
  ): Promise<Hub> {
    const res = await apiFetch<{ data: Hub }>(`/hubs/${id}`, {
      method: 'PUT',
      body: payload
    });
    return res.data;
  }

  async function deleteHub(id: number | string): Promise<void> {
    await apiFetch(`/hubs/${id}`, { method: 'DELETE' });
  }

  // ── Courts ─────────────────────────────────────────────────────────────────

  async function fetchCourts(hubId: number | string): Promise<Court[]> {
    const res = await apiFetch<{ data: Court[] }>(`/hubs/${hubId}/courts`);
    return res.data;
  }

  async function createCourt(
    hubId: number | string,
    payload: {
      name: string;
      surface?: string | null;
      indoor?: boolean;
      price_per_hour?: number;
      max_players?: number | null;
      is_active?: boolean;
      sports?: string[];
    }
  ): Promise<Court> {
    const res = await apiFetch<{ data: Court }>(`/hubs/${hubId}/courts`, {
      method: 'POST',
      body: payload
    });
    return res.data;
  }

  async function updateCourt(
    hubId: number | string,
    courtId: number | string,
    payload: Partial<{
      name: string;
      surface: string | null;
      indoor: boolean;
      price_per_hour: number;
      max_players: number | null;
      is_active: boolean;
      sports: string[];
    }>
  ): Promise<Court> {
    const res = await apiFetch<{ data: Court }>(
      `/hubs/${hubId}/courts/${courtId}`,
      {
        method: 'PUT',
        body: payload
      }
    );
    return res.data;
  }

  async function deleteCourt(
    hubId: number | string,
    courtId: number | string
  ): Promise<void> {
    await apiFetch(`/hubs/${hubId}/courts/${courtId}`, { method: 'DELETE' });
  }

  return {
    fetchHubs,
    fetchHub,
    fetchMyHubs,
    createHub,
    updateHub,
    deleteHub,
    fetchCourts,
    createCourt,
    updateCourt,
    deleteCourt
  };
}
