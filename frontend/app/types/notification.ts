export type NotificationActivityType =
  | 'booking_created'
  | 'receipt_uploaded'
  | 'booking_confirmed'
  | 'booking_rejected'
  | 'booking_cancelled';

export interface NotificationData {
  activity_type: NotificationActivityType;
  booking_id: string;
  booking_code: string;
  customer_name: string;
  court_name: string;
  hub_name: string;
  hub_id: string;
  start_time: string;
  message: string;
}

export interface AppNotification {
  id: string;
  activity_type: NotificationActivityType;
  data: NotificationData;
  read_at: string | null;
  created_at: string;
}
