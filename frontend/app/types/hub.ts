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

export interface HubWebsite {
  url: string;
}

export interface OperatingHoursEntry {
  day_of_week: number; // 0 = Sunday … 6 = Saturday
  opens_at: string;    // 'HH:mm'
  closes_at: string;   // 'HH:mm'
  is_closed: boolean;
}

/** Matches the Hub object returned by the API */
export interface Hub {
  id: string;
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
  gallery_images: { id: string; url: string; order: number }[];
  is_active: boolean;
  is_approved: boolean;
  is_verified: boolean;
  require_account_to_book: boolean;
  guest_booking_limit: number;
  guest_max_hours: number;
  payment_methods: Array<'pay_on_site' | 'digital_bank'>;
  payment_qr_url: string | null;
  digital_bank_name: string | null;
  digital_bank_account: string | null;
  owner_id: string;
  owner?: { id: string; name: string; avatar_url: string | null } | null;
  sports: SportType[];
  contact_numbers: HubContactNumber[];
  websites: HubWebsite[];
  courts_count: number;
  lowest_price_per_hour: string | null;
  operating_hours: OperatingHoursEntry[];
  created_at: string;

  // Rating stats from API
  rating: number | null;
  reviews_count: number;
  rating_breakdown?: Record<number, number> | null;

  // Client-side helpers used on legacy listing pages (computed from API data)
  coverImageUrl?: string;
  courtsCount?: number;
  lowestPricePerHour?: number;
  reviewsCount?: number;
  isOpenNow?: boolean;
}

export interface HubRating {
  id: string;
  rating: number;
  comment: string | null;
  created_at: string;
  court_name: string | null;
  user: { id: string; name: string; avatar_url: string | null };
}

export interface Court {
  id: string;
  hub_id: string;
  name: string;
  surface: SurfaceType | null;
  indoor: boolean;
  price_per_hour: string;
  is_active: boolean;
  sports: SportType[];
  image_url: string | null;
  created_at: string;
}

export interface CourtSport {
  id: string;
  court_id: string;
  sport: SportType;
}

export interface HubSport {
  id: string;
  hub_id: string;
  sport: SportType;
}

export interface PaginationMeta {
  total: number;
  current_page: number;
  last_page: number;
  per_page: number;
}
