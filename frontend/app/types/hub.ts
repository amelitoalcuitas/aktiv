export type SportType =
  | 'tennis'
  | 'badminton'
  | 'basketball'
  | 'pickleball'
  | 'volleyball';

export type SurfaceType =
  | 'hardcourt'
  | 'clay'
  | 'synthetic'
  | 'grass'
  | 'concrete'
  | 'wood';

export interface HubContactNumber {
  type: 'mobile' | 'landline';
  number: string;
}

/** Matches the Hub object returned by the API */
export interface Hub {
  id: number;
  name: string;
  description: string | null;
  city: string;
  zip_code: string | null;
  province: string | null;
  country: string | null;
  address: string;
  address_line2: string | null;
  landmark: string | null;
  lat: string | null;
  lng: string | null;
  cover_image_url: string | null;
  gallery_images: { id: number; url: string; order: number }[];
  is_approved: boolean;
  is_verified: boolean;
  owner_id: number;
  owner?: { id: number; name: string; avatar_url: string | null } | null;
  sports: SportType[];
  contact_numbers: HubContactNumber[];
  courts_count: number;
  lowest_price_per_hour: string | null;
  created_at: string;

  // Client-side helpers used on legacy listing pages (computed from API data)
  coverImageUrl?: string;
  courtsCount?: number;
  lowestPricePerHour?: number;
  rating?: number;
  reviewsCount?: number;
  isOpenNow?: boolean;
}

export interface Court {
  id: number;
  hub_id: number;
  name: string;
  surface: SurfaceType | null;
  indoor: boolean;
  price_per_hour: string;
  max_players: number | null;
  is_active: boolean;
  sports: SportType[];
  created_at: string;
}

export interface CourtSport {
  id: number;
  court_id: number;
  sport: SportType;
}

export interface HubSport {
  id: number;
  hub_id: number;
  sport: SportType;
}
