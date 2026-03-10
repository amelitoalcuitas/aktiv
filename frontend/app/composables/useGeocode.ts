/**
 * useGeocode — Google Maps Reverse Geocoding with credit-saving techniques:
 *
 *  1. Coordinates rounded to 3 decimal places (~110 m precision) as cache key.
 *  2. In-memory Map cache — same rounded key never hits the API twice per session.
 *  3. Haversine distance gate — skips the API if new pin is within 100 m of the
 *     last successfully geocoded point.
 *  4. 600 ms debounce exposed via `debouncedReverseGeocode` — absorbs rapid
 *     pin-drag events so only the resting position fires the API.
 *
 *  Address building strategy:
 *  - address  → Address Line 1: "{streetNumber} {road}" or neighbourhood or city
 *  - address2 → Address Line 2: neighbourhood (only when line 1 has a street)
 *  - province → administrative_area_level_2 (e.g. "Zamboanga del Sur")
 *  - state    → administrative_area_level_1 (region, e.g. "Zamboanga Peninsula")
 *  - Plus Codes (e.g. "RFP2+W7W") are detected and never exposed to the UI.
 */

export interface GeocodeResult {
  lat: number;
  lng: number;
  city: string;
  /** Address line 1: street number + road name, or neighbourhood, or city. Never a Plus Code. */
  address: string;
  /** Address line 2: neighbourhood/sublocality — populated only when line 1 has a street. */
  address2: string;
  road: string;
  streetNumber: string;
  neighbourhood: string;
  /** administrative_area_level_2 — province/district (e.g. "Zamboanga del Sur") */
  province: string;
  /** administrative_area_level_1 — region (e.g. "Zamboanga Peninsula") */
  state: string;
  postcode: string;
  country: string;
  formattedAddress: string;
}

// ── helpers ──────────────────────────────────────────────────────────────────

function cacheKey(lat: number, lng: number): string {
  return `${lat.toFixed(3)},${lng.toFixed(3)}`;
}

/** Haversine distance in metres between two lat/lng pairs. */
function distanceMetres(
  lat1: number,
  lng1: number,
  lat2: number,
  lng2: number
): number {
  const R = 6_371_000;
  const φ1 = (lat1 * Math.PI) / 180;
  const φ2 = (lat2 * Math.PI) / 180;
  const Δφ = ((lat2 - lat1) * Math.PI) / 180;
  const Δλ = ((lng2 - lng1) * Math.PI) / 180;
  const a =
    Math.sin(Δφ / 2) ** 2 + Math.cos(φ1) * Math.cos(φ2) * Math.sin(Δλ / 2) ** 2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function getComponent(
  components: Array<{ long_name: string; short_name: string; types: string[] }>,
  ...types: string[]
): string {
  for (const type of types) {
    const found = components.find((c) => c.types.includes(type));
    if (found) return found.long_name;
  }
  return '';
}

/** Returns true if the string looks like a Google Plus Code (e.g. "RFP2+W7W"). */
function isPlusCode(value: string): boolean {
  return /^[A-Z0-9]{4}\+[A-Z0-9]{2,}/.test(value.trim());
}

// ── module-level state (shared across all usages per page load) ───────────────

const cache = new Map<string, GeocodeResult>();
let lastGeocodedPoint: { lat: number; lng: number } | null = null;
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

// ── composable ────────────────────────────────────────────────────────────────

export function useGeocode() {
  const config = useRuntimeConfig();

  async function reverseGeocode(
    lat: number,
    lng: number
  ): Promise<GeocodeResult | null> {
    const key = cacheKey(lat, lng);

    // 1. Cache hit
    if (cache.has(key)) {
      return cache.get(key)!;
    }

    // 2. Distance gate — within 100 m of last geocoded point → reuse cached result
    if (lastGeocodedPoint) {
      const d = distanceMetres(
        lat,
        lng,
        lastGeocodedPoint.lat,
        lastGeocodedPoint.lng
      );
      if (d < 100) {
        const nearbyKey = cacheKey(
          lastGeocodedPoint.lat,
          lastGeocodedPoint.lng
        );
        const cached = cache.get(nearbyKey);
        if (cached) return cached;
      }
    }

    // 3. Call Google Geocoding API
    const apiKey = config.public.googleMapsKey;
    const url = `https://maps.googleapis.com/maps/api/geocode/json?latlng=${lat},${lng}&key=${apiKey}`;

    let data: {
      status: string;
      results: Array<{
        formatted_address: string;
        address_components: Array<{
          long_name: string;
          short_name: string;
          types: string[];
        }>;
      }>;
    };

    try {
      data = await $fetch(url);
    } catch {
      return null;
    }

    if (data.status !== 'OK' || !data.results.length) return null;

    // Prefer a result that has an actual route over a Plus Code result
    const bestResult =
      data.results.find((r) =>
        r.address_components.some((c) => c.types.includes('route'))
      ) ?? data.results[0]!;

    const comps = bestResult.address_components;

    // ── extract individual components ─────────────────────────────────────────
    const streetNumber = getComponent(comps, 'street_number');
    const road = getComponent(comps, 'route');
    const neighbourhood = getComponent(
      comps,
      'neighborhood',
      'sublocality_level_2', // barangay level in PH
      'sublocality_level_1',
      'sublocality'
    );
    const city = getComponent(
      comps,
      'locality',
      'administrative_area_level_2',
      'administrative_area_level_1'
    );

    // ── build address lines ───────────────────────────────────────────────────
    const streetLine = [streetNumber, road].filter(Boolean).join(' ');
    const rawAddress1 =
      streetLine || neighbourhood || city || bestResult.formatted_address;

    // Ensure line 1 never exposes a Plus Code to the UI
    const address = isPlusCode(rawAddress1)
      ? neighbourhood || city || ''
      : rawAddress1;

    // Line 2: neighbourhood only when line 1 already consumed the street
    const address2 = streetLine && neighbourhood ? neighbourhood : '';

    // ── assemble result ───────────────────────────────────────────────────────
    const geocoded: GeocodeResult = {
      lat,
      lng,
      city,
      address,
      address2,
      road,
      streetNumber,
      neighbourhood,
      province: getComponent(comps, 'administrative_area_level_2'), // e.g. "Zamboanga del Sur"
      state: getComponent(comps, 'administrative_area_level_1'), // e.g. "Zamboanga Peninsula"
      postcode: getComponent(comps, 'postal_code'),
      country: getComponent(comps, 'country'),
      formattedAddress: bestResult.formatted_address
    };

    cache.set(key, geocoded);
    lastGeocodedPoint = { lat, lng };

    return geocoded;
  }

  /**
   * Debounced wrapper — 600 ms delay. Use this inside the map picker to avoid
   * firing on every intermediate drag position.
   */
  function debouncedReverseGeocode(
    lat: number,
    lng: number,
    onResult: (result: GeocodeResult | null) => void
  ): void {
    if (debounceTimer !== null) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(async () => {
      const result = await reverseGeocode(lat, lng);
      onResult(result);
    }, 600);
  }

  return { reverseGeocode, debouncedReverseGeocode };
}
