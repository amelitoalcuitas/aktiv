import { useApi } from '~/utils/api';

export interface ApproximateLocation {
  source: 'ip';
  accuracy: 'approximate';
  lat: number;
  lng: number;
  city: string | null;
  region: string | null;
  country: string | null;
  timezone: string | null;
}

interface CachedApproximateLocation {
  expiresAt: number;
  value: ApproximateLocation;
}

const STORAGE_KEY = 'aktiv.approximate-location';

export function useApproximateLocation() {
  const { apiFetch } = useApi();
  const config = useRuntimeConfig();

  function getCachedApproximateLocation(): ApproximateLocation | null {
    if (!import.meta.client) return null;

    const raw = window.sessionStorage.getItem(STORAGE_KEY);
    if (!raw) return null;

    try {
      const parsed = JSON.parse(raw) as CachedApproximateLocation;
      if (
        !parsed ||
        typeof parsed.expiresAt !== 'number' ||
        !parsed.value ||
        parsed.expiresAt <= Date.now()
      ) {
        window.sessionStorage.removeItem(STORAGE_KEY);
        return null;
      }

      return parsed.value;
    } catch {
      window.sessionStorage.removeItem(STORAGE_KEY);
      return null;
    }
  }

  function cacheApproximateLocation(location: ApproximateLocation): void {
    if (!import.meta.client) return;

    const ttlMs = Math.max(
      60_000,
      Number(config.public.approximateLocationCacheTtlMs ?? 900_000)
    );

    window.sessionStorage.setItem(
      STORAGE_KEY,
      JSON.stringify({
        expiresAt: Date.now() + ttlMs,
        value: location
      } satisfies CachedApproximateLocation)
    );
  }

  async function fetchApproximateLocation(): Promise<ApproximateLocation | null> {
    const cached = getCachedApproximateLocation();
    if (cached) return cached;

    try {
      const response = await apiFetch<{ data: ApproximateLocation | null }>(
        '/location/approx'
      );

      if (response.data) {
        cacheApproximateLocation(response.data);
      }

      return response.data;
    } catch {
      return null;
    }
  }

  return {
    fetchApproximateLocation,
    getCachedApproximateLocation
  };
}
