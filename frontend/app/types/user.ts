export type UserRole = 'user' | 'admin' | 'super_admin';

export type SocialPlatform = 'facebook' | 'instagram' | 'x' | 'youtube' | 'threads' | 'other';

export interface SocialLink {
  platform: SocialPlatform;
  url: string;
}

export interface SocialLinks {
  facebook?: string | null;
  instagram?: string | null;
  x?: string | null;
  youtube?: string | null;
  threads?: string | null;
  other?: string | null;
}

export interface ProfilePrivacy {
  show_owned_hubs: boolean;
  show_visited_hubs: boolean;
  show_leaderboard: boolean;
  show_hearts: boolean;
  show_tournaments: boolean;
  show_open_play: boolean;
  show_favorite_sports: boolean;
}

export interface OwnedHub {
  id: string;
  name: string;
  description: string | null;
  city: string;
  cover_image_url: string | null;
  rating: number | null;
  show_on_profile?: boolean;
}

export interface User {
  id: string;
  first_name: string;
  last_name: string;
  username: string | null;
  username_changed_at: string | null;
  name_changed_at: string | null;
  email: string;
  avatar_url: string | null;
  avatar_thumb_url: string | null;
  banner_url: string | null;
  contact_number: string | null;
  bio: string | null;
  social_links: SocialLinks;
  profile_privacy: ProfilePrivacy;
  google_id: string | null;
  role: UserRole;
  email_verified_at: string | null;
  expired_booking_strikes: number;
  booking_banned_until: string | null;
  is_hub_owner: boolean;
  owned_hubs: OwnedHub[];
  hub_display_order: string[];
  hearts_count: number;
  created_at: string;
}

export interface PublicUser {
  id: string;
  first_name: string;
  last_name: string;
  username: string | null;
  avatar_url: string | null;
  banner_url: string | null;
  bio: string | null;
  social_links: SocialLinks;
  is_hub_owner: boolean;
  owned_hubs: OwnedHub[];
  hearts_count: number | null;
  has_hearted: boolean;
  privacy: ProfilePrivacy;
  created_at: string;
}
