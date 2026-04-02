export type BookingStatus =
  | 'pending_payment'
  | 'payment_sent'
  | 'confirmed'
  | 'cancelled'
  | 'completed';

export type SessionType = 'private' | 'open_play';
export type BookingSource = 'self_booked' | 'owner_added';
export type PaymentMethod = 'gcash' | 'bank_transfer' | 'pay_on_site' | string;
export type CancelledBy = 'user' | 'owner' | 'system';

export interface Booking {
  id: string;
  booking_code: string | null;
  court_id: string;
  booked_by: string | null;
  sport: string;
  start_time: string;
  end_time: string;
  session_type: SessionType;
  status: BookingStatus;
  booking_source: BookingSource;
  payment_method: PaymentMethod | null;
  created_by: string | null;
  guest_name: string | null;
  guest_phone: string | null;
  guest_email: string | null;
  total_price: string | null;
  original_price: string | null;
  discount_amount: string | null;
  applied_promo_title: string | null;
  receipt_image_url: string | null;
  receipt_uploaded_at: string | null;
  payment_note: string | null;
  payment_confirmed_by: string | null;
  payment_confirmed_at: string | null;
  expires_at: string | null;
  cancelled_by: CancelledBy | null;
  created_at: string;
  guest_tracking_token?: string | null;
}

export interface AppliedDiscount {
  source: 'promo' | 'voucher';
  title: string | null;
  label: string;
  code: string | null;
  event_id?: string | null;
  discount_type: 'percent' | 'flat';
  discount_value: number;
  overrides_promo: boolean;
}

export interface VoucherPreviewItem {
  court_id: string;
  start_time: string;
  end_time: string;
  original_price: number;
  discounted_price: number;
  discount_amount: number;
}

export interface VoucherPreview {
  voucher_code: string;
  summary: {
    original_total: number;
    discounted_total: number;
    discount_amount: number;
  };
  applied_discount: AppliedDiscount | null;
  items: VoucherPreviewItem[];
}

/** Full booking shape returned by owner dashboard endpoints (includes eager-loaded relations). */
export interface BookingDetail extends Booking {
  court: { id: string; name: string; hub_id: string } | null;
  open_play_session_id?: string | null;
  open_play_session?: import('./openPlay').OpenPlaySession | null;
  booked_by_user: {
    id: string;
    first_name: string;
    last_name: string;
    email: string;
    contact_number: string | null;
    avatar_url: string | null;
  } | null;
}

/** A time slot the user has selected in the resource grid (UI state only). */
export interface SelectedSlot {
  courtId: string;
  slotStart: Date; // exact start of the 1-hour slot
}

/** Booking shape returned by the user's own booking list endpoint (includes court + hub). */
export interface UserBooking extends Booking {
  court: {
    id: string;
    name: string;
    hub: { id: string; username?: string | null; name: string; cover_image_url?: string | null } | null;
  } | null;
}

export type MyBookingEntryType = 'booking' | 'open_play_participant';

export interface MyBookingItem {
  id: string;
  entry_type: MyBookingEntryType;
  participant_id: string | null;
  booking_id: string | null;
  session_id: string | null;
  booking_code: string | null;
  sport: string | null;
  start_time: string;
  end_time: string;
  session_type: SessionType;
  status: BookingStatus;
  booking_source: BookingSource | null;
  payment_method: PaymentMethod | null;
  total_price: string | null;
  price_per_player: string | null;
  original_price: string | null;
  discount_amount: string | null;
  applied_promo_title: string | null;
  receipt_image_url: string | null;
  receipt_uploaded_at: string | null;
  payment_note: string | null;
  expires_at: string | null;
  cancelled_by: CancelledBy | null;
  participants_count: number | null;
  max_players: number | null;
  is_open_play_join: boolean;
  created_at: string;
  court: {
    id: string;
    name: string;
    hub: { id: string; username?: string | null; name: string; cover_image_url?: string | null } | null;
  } | null;
}

/** Minimal booking shape for rendering the calendar (no PII). */
export interface CalendarBooking {
  id: string;
  start_time: string;
  end_time: string;
  session_type: SessionType;
  status: BookingStatus;
  is_own: boolean;
  court_id?: string;
  expires_at?: string | null;
}
