export type NotificationActivityType =
  | 'booking_created'
  | 'receipt_uploaded'
  | 'booking_confirmed'
  | 'booking_rejected'
  | 'booking_cancelled'
  | 'booking_cancelled_by_guest'
  | 'open_play_receipt_uploaded'
  | 'open_play_participant_confirmed'
  | 'open_play_participant_rejected'
  | 'open_play_participant_cancelled'
  | 'open_play_session_cancelled'
  | 'open_play_session_started';

export interface NotificationData {
  activity_type: NotificationActivityType;
  item_id?: string;
  booking_id?: string;
  booking_code?: string;
  customer_name?: string;
  court_name: string;
  hub_name: string;
  hub_id: string;
  start_time: string;
  message: string;
  session_id?: string;
}

export interface AppNotification {
  id: string;
  activity_type: NotificationActivityType;
  data: NotificationData;
  read_at: string | null;
  created_at: string;
}
