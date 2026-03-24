import type { Hub, Court, HubRating, HubContactNumber, HubWebsite, OperatingHoursEntry, PaginationMeta } from '~/types/hub';
import { useApi } from '~/utils/api';

/**
 * Returns true if the hub is currently open based on its operating_hours.
 * Times in operating_hours are stored in Asia/Manila local time (HH:mm),
 * and compared against the current local clock — matching HubProfileHeader's pattern.
 */
export function isHubOpenNow(hub: Hub): boolean {
  const hours = hub.operating_hours;
  if (!hours?.length) return false;
  const now = new Date();
  const todayHours = hours.find((oh) => oh.day_of_week === now.getDay());
  if (!todayHours || todayHours.is_closed) return false;
  const [openH, openM] = todayHours.opens_at.split(':').map(Number);
  const [closeH, closeM] = todayHours.closes_at.split(':').map(Number);
  const nowMins = now.getHours() * 60 + now.getMinutes();
  return nowMins >= openH * 60 + openM && nowMins < closeH * 60 + closeM;
}

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
      contact_numbers: HubContactNumber[];
      websites: HubWebsite[];
      cover_image: File | null;
      gallery_images: File[];
      remove_gallery_image_ids: number[];
      operating_hours: OperatingHoursEntry[];
      guest_booking_limit: number;
      guest_max_hours: number;
      payment_methods: Array<'pay_on_site' | 'digital_bank'>;
      payment_qr_image: File | null;
      digital_bank_name: string | null;
      digital_bank_account: string | null;
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
    if (payload.require_account_to_book !== undefined) {
      formData.append('require_account_to_book', payload.require_account_to_book ? '1' : '0');
    }
    appendIfDefined('guest_booking_limit', payload.guest_booking_limit);
    appendIfDefined('guest_max_hours', payload.guest_max_hours);

    if (payload.cover_image) {
      validateImageSize(payload.cover_image, 'Cover image');
      formData.append('cover_image', payload.cover_image);
    }

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
    (payload.operating_hours ?? []).forEach((oh, i) => {
      formData.append(`operating_hours[${i}][day_of_week]`, String(oh.day_of_week));
      formData.append(`operating_hours[${i}][opens_at]`, oh.opens_at);
      formData.append(`operating_hours[${i}][closes_at]`, oh.closes_at);
      formData.append(`operating_hours[${i}][is_closed]`, oh.is_closed ? '1' : '0');
    });

    (payload.payment_methods ?? []).forEach((method) =>
      formData.append('payment_methods[]', method)
    );

    if (payload.payment_qr_image) {
      validateImageSize(payload.payment_qr_image, 'Payment QR image');
      formData.append('payment_qr_image', payload.payment_qr_image);
    }

    if (payload.remove_payment_qr) {
      formData.append('remove_payment_qr', '1');
    }

    appendIfDefined('digital_bank_name', payload.digital_bank_name);
    appendIfDefined('digital_bank_account', payload.digital_bank_account);
  }

  // ── Hubs ──────────────────────────────────────────────────────────────────

  async function fetchHubs(): Promise<Hub[]> {
    const res = await apiFetch<{ data: Hub[] }>('/hubs');
    return res.data;
  }

  async function fetchHubsPaginated(params: {
    page?: number;
    per_page?: number;
    city?: string;
    sports?: string[];
    search?: string;
    limit?: number;
    sort?: string;
    lat?: number;
    lng?: number;
    radius?: number;
  }): Promise<{ data: Hub[]; meta?: PaginationMeta; suggestions?: Hub[] }> {
    const query = new URLSearchParams();
    if (params.page) query.set('page', String(params.page));
    if (params.per_page) query.set('per_page', String(params.per_page));
    if (params.city) query.set('city', params.city);
    if (params.search) query.set('search', params.search);
    if (params.limit) query.set('limit', String(params.limit));
    if (params.sort) query.set('sort', params.sort);
    if (params.lat != null) query.set('lat', String(params.lat));
    if (params.lng != null) query.set('lng', String(params.lng));
    if (params.radius != null) query.set('radius', String(params.radius));
    (params.sports ?? []).forEach((s) => query.append('sports[]', s));
    const qs = query.toString();
    const res = await apiFetch<{ data: Hub[]; meta?: PaginationMeta; suggestions?: Hub[] }>(`/hubs${qs ? `?${qs}` : ''}`);
    return { data: res.data, meta: res.meta, suggestions: res.suggestions };
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
    require_account_to_book?: boolean;
    cover_image?: File | null;
    gallery_images?: File[];
    sports?: string[];
    contact_numbers?: HubContactNumber[];
    websites?: HubWebsite[];
    operating_hours?: OperatingHoursEntry[];
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
      require_account_to_book: boolean;
      guest_booking_limit: number;
      guest_max_hours: number;
      contact_numbers: HubContactNumber[];
      websites: HubWebsite[];
      operating_hours: OperatingHoursEntry[];
      payment_methods: Array<'pay_on_site' | 'digital_bank'>;
      payment_qr_image: File | null;
      remove_payment_qr: boolean;
      digital_bank_name: string | null;
      digital_bank_account: string | null;
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
      court_image?: File | null;
    }
  ): Promise<Court> {
    const { court_image, ...rest } = payload;
    let body: FormData | typeof rest = rest;

    if (court_image) {
      const fd = new FormData();
      Object.entries(rest).forEach(([k, v]) => {
        if (v === null || v === undefined) return;
        if (Array.isArray(v)) {
          v.forEach((item) => fd.append(`${k}[]`, String(item)));
        } else if (typeof v === 'boolean') {
          fd.append(k, v ? '1' : '0');
        } else {
          fd.append(k, String(v));
        }
      });
      fd.append('court_image', court_image);
      body = fd;
    }

    const res = await apiFetch<{ data: Court }>(`/hubs/${hubId}/courts`, {
      method: 'POST',
      body
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
      court_image: File | null;
      remove_court_image: boolean;
    }>
  ): Promise<Court> {
    const { court_image, remove_court_image, ...rest } = payload;
    const needsFormData = !!court_image || !!remove_court_image;

    let body: FormData | (typeof rest & { _method?: string });

    if (needsFormData) {
      const fd = new FormData();
      fd.append('_method', 'PUT');
      Object.entries(rest).forEach(([k, v]) => {
        if (v === null || v === undefined) return;
        if (Array.isArray(v)) {
          v.forEach((item) => fd.append(`${k}[]`, String(item)));
        } else if (typeof v === 'boolean') {
          fd.append(k, v ? '1' : '0');
        } else {
          fd.append(k, String(v));
        }
      });
      if (court_image) fd.append('court_image', court_image);
      if (remove_court_image) fd.append('remove_court_image', '1');
      body = fd;

      const res = await apiFetch<{ data: Court }>(
        `/hubs/${hubId}/courts/${courtId}`,
        { method: 'POST', body }
      );
      return res.data;
    }

    const res = await apiFetch<{ data: Court }>(
      `/hubs/${hubId}/courts/${courtId}`,
      { method: 'PUT', body: rest }
    );
    return res.data;
  }

  async function deleteCourt(
    hubId: number | string,
    courtId: number | string
  ): Promise<void> {
    await apiFetch(`/hubs/${hubId}/courts/${courtId}`, { method: 'DELETE' });
  }

  // ── Ratings ────────────────────────────────────────────────────────────────

  async function fetchHubRatings(
    hubId: number | string,
    cursor?: string,
    sort?: 'newest' | 'highest' | 'lowest',
    court?: string | null
  ): Promise<{ data: HubRating[]; next_cursor: string | null }> {
    const params = new URLSearchParams();
    if (cursor) params.set('cursor', cursor);
    if (sort) params.set('sort', sort);
    if (court) params.set('court', court);
    const qs = params.toString();
    const res = await apiFetch<{ data: HubRating[]; next_cursor: string | null }>(
      `/hubs/${hubId}/ratings${qs ? `?${qs}` : ''}`
    );
    return res;
  }

  async function fetchHubRatingCourts(hubId: number | string): Promise<string[]> {
    const res = await apiFetch<{ data: string[] }>(`/hubs/${hubId}/ratings/courts`);
    return res.data;
  }

  async function submitHubRating(
    hubId: number | string,
    rating: number,
    comment?: string | null,
    bookingId?: number | null
  ): Promise<HubRating> {
    const res = await apiFetch<{ data: HubRating }>(`/hubs/${hubId}/ratings`, {
      method: 'POST',
      body: { rating, comment: comment ?? null, booking_id: bookingId ?? null }
    });
    return res.data;
  }

  async function fetchPendingReview(
    testBookingId?: number
  ): Promise<{ bookings: import('~/types/booking').UserBooking[] }> {
    const qs = testBookingId ? `?test_booking_id=${testBookingId}` : '';
    return apiFetch<{ bookings: import('~/types/booking').UserBooking[] }>(
      `/user/pending-review${qs}`
    );
  }

  async function skipBookingReview(bookingId: number): Promise<void> {
    await apiFetch('/user/booking-review-skip', {
      method: 'POST',
      body: { booking_id: bookingId },
    });
  }

  return {
    fetchHubs,
    fetchHubsPaginated,
    fetchHub,
    fetchMyHubs,
    createHub,
    updateHub,
    deleteHub,
    fetchCourts,
    createCourt,
    updateCourt,
    deleteCourt,
    fetchHubRatings,
    fetchHubRatingCourts,
    submitHubRating,
    fetchPendingReview,
    skipBookingReview
  };
}
