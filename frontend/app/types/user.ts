export type UserRole = 'user' | 'admin' | 'super_admin';

export interface User {
  id: string;
  name: string;
  email: string;
  avatar_url: string | null;
  phone: string | null;
  google_id: string | null;
  role: UserRole;
  email_verified_at: string | null;
  expired_booking_strikes: number;
  booking_banned_until: string | null;
  created_at: string;
}
