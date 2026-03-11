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

/** Minimal booking shape for rendering the calendar (no PII). */
export interface CalendarBooking {
  id: number;
  start_time: string;
  end_time: string;
  session_type: SessionType;
  status: BookingStatus;
  is_own: boolean;
}
