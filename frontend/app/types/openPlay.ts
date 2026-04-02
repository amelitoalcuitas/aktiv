export type OpenPlayStatus = 'open' | 'full' | 'cancelled' | 'completed'
export type ParticipantPaymentStatus =
  | 'pending_payment'
  | 'payment_sent'
  | 'confirmed'
  | 'cancelled'

export interface OpenPlaySession {
  id: string
  booking_id: string
  title: string
  description: string | null
  max_players: number
  price_per_player: string
  notes: string | null
  guests_can_join: boolean
  status: OpenPlayStatus
  booking: {
    id: string
    court_id: string
    court: { id: string; name: string } | null
    start_time: string
    end_time: string
    status: string
  } | null
  participants_count: number
  confirmed_participants_count: number
  viewer_participant: OpenPlayParticipant | null
  created_at: string
}

export interface OpenPlayParticipant {
  id: string
  open_play_session_id: string
  user_id: string | null
  user: {
    id: string
    first_name: string
    last_name: string
    email: string
    contact_number: string | null
    avatar_url: string | null
  } | null
  guest_name: string | null
  guest_phone: string | null
  guest_email: string | null
  guest_tracking_token: string | null
  payment_method: 'pay_on_site' | 'digital_bank'
  payment_status: ParticipantPaymentStatus
  receipt_image_url: string | null
  receipt_uploaded_at: string | null
  payment_note: string | null
  payment_confirmed_by: string | null
  payment_confirmed_at: string | null
  expires_at: string | null
  cancelled_by: 'user' | 'owner' | 'system' | null
  joined_at: string
  created_at: string
}
