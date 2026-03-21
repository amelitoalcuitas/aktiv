export type BookingStatus =
  | 'pending_payment'
  | 'payment_sent'
  | 'confirmed'
  | 'cancelled'
  | 'completed';

export type SessionType = 'private' | 'open_play';
export type BookingSource = 'self_booked' | 'owner_added';
export type CancelledBy = 'user' | 'owner' | 'system';

export interface Booking {
  id: number;
  booking_code: string | null;
  court_id: number;
  booked_by: number | null;
  sport: string;
  start_time: string;
  end_time: string;
  session_type: SessionType;
  status: BookingStatus;
  booking_source: BookingSource;
  created_by: number | null;
  guest_name: string | null;
  guest_phone: string | null;
  guest_email: string | null;
  total_price: string | null;
  receipt_image_url: string | null;
  receipt_uploaded_at: string | null;
  payment_note: string | null;
  payment_confirmed_by: number | null;
  payment_confirmed_at: string | null;
  expires_at: string | null;
  cancelled_by: CancelledBy | null;
  created_at: string;
}

/** Full booking shape returned by owner dashboard endpoints (includes eager-loaded relations). */
export interface BookingDetail extends Booking {
  court: { id: number; name: string; hub_id: number } | null;
  booked_by_user: {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    avatar_url: string | null;
  } | null;
}

/** A time slot the user has selected in the resource grid (UI state only). */
export interface SelectedSlot {
  courtId: number;
  slotStart: Date; // exact start of the 1-hour slot
}

/** Booking shape returned by the user's own booking list endpoint (includes court + hub). */
export interface UserBooking extends Booking {
  court: {
    id: number;
    name: string;
    hub: { id: number; name: string; cover_image_url?: string | null } | null;
  } | null;
}

/** Minimal booking shape for rendering the calendar (no PII). */
export interface CalendarBooking {
  id: number;
  start_time: string;
  end_time: string;
  session_type: SessionType;
  status: BookingStatus;
  is_own: boolean;
  court_id?: number;
  expires_at?: string | null;
}
