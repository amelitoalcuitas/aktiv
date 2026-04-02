import type { LinkPlatform, LinkRow } from '~/types/links';

export type UserRole = 'user' | 'owner' | 'super_admin';
export type HubOwnerRequestStatus = 'none' | 'pending' | 'approved' | 'rejected';

export type SocialPlatform = LinkPlatform;

export interface SocialLink extends LinkRow {}

export interface SocialLinks {
  facebook?: string | null;
  instagram?: string | null;
  x?: string | null;
  youtube?: string | null;
  threads?: string | null;
  other?: string | null;
}

export interface ProfilePrivacy {
  profile_visible_to: 'everyone' | 'no_one';
  show_full_name: boolean;
  show_owned_hubs: boolean;
  show_visited_hubs: boolean;
  show_leaderboard: boolean;
  show_hearts: boolean;
  show_tournaments: boolean;
  show_open_play: boolean;
  show_favorite_sports: boolean;
  show_joined_hubs: boolean;
}

export interface JoinedHub {
  id: string;
  username: string | null;
  name: string;
  city: string;
  cover_image_url: string | null;
}

export interface OwnedHub {
  id: string;
  username: string | null;
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
  country: string | null;
  province: string | null;
  city: string | null;
  bio: string | null;
  social_links: SocialLinks;
  profile_privacy: ProfilePrivacy;
  google_id: string | null;
  role: UserRole;
  email_verified_at: string | null;
  expired_booking_strikes: number;
  booking_banned_until: string | null;
  is_hub_owner: boolean;
  is_premium: boolean;
  owned_hubs: OwnedHub[];
  joined_hubs: JoinedHub[];
  hub_display_order: string[];
  hearts_count: number;
  created_at: string;
  deletion_scheduled_at: string | null;
  hub_owner_request_status: HubOwnerRequestStatus;
  has_password: boolean;
}

export interface PublicUser {
  id: string;
  is_private?: boolean;
  first_name: string;
  last_name: string;
  username: string | null;
  avatar_url: string | null;
  banner_url: string | null;
  bio: string | null;
  social_links: SocialLinks;
  is_hub_owner: boolean;
  is_premium: boolean;
  owned_hubs: OwnedHub[];
  joined_hubs: JoinedHub[];
  hearts_count: number | null;
  has_hearted: boolean;
  privacy: ProfilePrivacy;
  created_at: string;
}
