import type { Hub, Court, HubContactNumber, HubWebsite } from '~/types/hub';
import { useApi } from '~/utils/api';

export const HUB_IMAGE_MAX_SIZE_MB = 10;
export const HUB_IMAGE_MAX_BYTES = HUB_IMAGE_MAX_SIZE_MB * 1024 * 1024;

export function useHubs() {
  const { apiFetch } = useApi();

  function validateImageSize(file: File, fieldLabel: string) {
    if (file.size > HUB_IMAGE_MAX_BYTES) {
      throw new Error(
        `${fieldLabel} must be ${HUB_IMAGE_MAX_SIZE_MB}MB or smaller.`
      );
    }
  }

  function appendHubFormData(
    formData: FormData,
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
      is_active: boolean;
      sports: string[];
      contact_numbers: HubContactNumber[];
      websites: HubWebsite[];
      cover_image: File | null;
      gallery_images: File[];
      remove_gallery_image_ids: number[];
    }>
  ) {
    const appendIfDefined = (key: string, value: unknown) => {
      if (value === undefined) return;
      if (value === null) {
        formData.append(key, '');
        return;
      }

      formData.append(key, String(value));
    };

    appendIfDefined('name', payload.name);
    appendIfDefined('description', payload.description);
    appendIfDefined('city', payload.city);
    appendIfDefined('zip_code', payload.zip_code);
    appendIfDefined('province', payload.province);
    appendIfDefined('country', payload.country);
    appendIfDefined('address', payload.address);
    appendIfDefined('address_line2', payload.address_line2);
    appendIfDefined('landmark', payload.landmark);
    appendIfDefined('lat', payload.lat);
    appendIfDefined('lng', payload.lng);
    if (payload.is_active !== undefined) {
      formData.append('is_active', payload.is_active ? '1' : '0');
    }

    if (payload.cover_image) {
      validateImageSize(payload.cover_image, 'Cover image');
      formData.append('cover_image', payload.cover_image);
    }

    (payload.sports ?? []).forEach((sport) =>
      formData.append('sports[]', sport)
    );
    (payload.contact_numbers ?? []).forEach((entry, i) => {
      formData.append(`contact_numbers[${i}][type]`, entry.type);
      formData.append(`contact_numbers[${i}][number]`, entry.number);
    });
    (payload.websites ?? []).forEach((entry, i) => {
      formData.append(`websites[${i}][url]`, entry.url);
    });
    (payload.gallery_images ?? []).forEach((image) => {
      validateImageSize(image, 'Gallery image');
      formData.append('gallery_images[]', image);
    });
    (payload.remove_gallery_image_ids ?? []).forEach((id) =>
      formData.append('remove_gallery_image_ids[]', String(id))
    );
  }

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
    is_active?: boolean;
    cover_image?: File | null;
    gallery_images?: File[];
    sports?: string[];
    contact_numbers?: HubContactNumber[];
    websites?: HubWebsite[];
  }): Promise<Hub> {
    const formData = new FormData();
    appendHubFormData(formData, payload);

    const res = await apiFetch<{ data: Hub }>('/hubs', {
      method: 'POST',
      body: formData
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
      cover_image: File | null;
      gallery_images: File[];
      remove_gallery_image_ids: number[];
      is_active: boolean;
      sports: string[];
      contact_numbers: HubContactNumber[];
      websites: HubWebsite[];
    }>
  ): Promise<Hub> {
    const formData = new FormData();
    formData.append('_method', 'PUT');
    appendHubFormData(formData, payload);

    const res = await apiFetch<{ data: Hub }>(`/hubs/${id}`, {
      method: 'POST',
      body: formData
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
      open_play_price_per_head?: number | null;
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
      open_play_price_per_head: number | null;
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
