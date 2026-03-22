<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingActivityNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Booking $booking,
        private readonly string $activityType,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if ($notifiable->inapp_notifications_enabled ?? true) {
            $channels[] = 'database';
        }

        $emailable = ['receipt_uploaded', 'booking_confirmed', 'booking_rejected', 'booking_cancelled', 'booking_cancelled_by_guest'];
        if (in_array($this->activityType, $emailable) && ($notifiable->email_notifications_enabled ?? true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking     = $this->booking;
        $hub         = $booking->court->hub;
        $courtName   = $booking->court->name;
        $frontendUrl = config('app.frontend_url');

        $subject = match ($this->activityType) {
            'receipt_uploaded'           => "Receipt Uploaded – {$booking->booking_code}",
            'booking_confirmed'          => "Booking Confirmed – {$booking->booking_code}",
            'booking_rejected'           => "Receipt Rejected – {$booking->booking_code}",
            'booking_cancelled',
            'booking_cancelled_by_guest' => "Booking Cancelled – {$booking->booking_code}",
            default                      => "Booking Update – {$booking->booking_code}",
        };

        $view = match ($this->activityType) {
            'receipt_uploaded'           => 'emails.receipt-uploaded-notification',
            'booking_cancelled_by_guest' => 'emails.guest-cancelled-booking-notification',
            default                      => 'emails.booking-status-update',
        };

        return (new MailMessage)
            ->subject($subject)
            ->view($view, compact('booking', 'hub', 'courtName', 'frontendUrl'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $booking = $this->booking;

        $customerName = $booking->bookedBy?->name
            ?? $booking->guest_name
            ?? 'Guest';

        $courtName = $booking->court->name;
        $hubName = $booking->court->hub->name;
        $hubId = $booking->court->hub_id;

        $message = match ($this->activityType) {
            'booking_created'             => "{$customerName} made a new booking on {$courtName}.",
            'receipt_uploaded'            => "{$customerName} uploaded a payment receipt for their booking on {$courtName}.",
            'booking_cancelled_by_guest'  => "{$customerName} cancelled their booking on {$courtName} at {$hubName}.",
            'booking_confirmed'           => "Your booking on {$courtName} at {$hubName} has been confirmed.",
            'booking_rejected'            => "Your booking on {$courtName} at {$hubName} was rejected. Please re-upload your receipt.",
            'booking_cancelled'           => "Your booking on {$courtName} at {$hubName} has been cancelled.",
            default                       => "Your booking on {$courtName} has been updated.",
        };

        return [
            'activity_type' => $this->activityType,
            'booking_id'    => $booking->id,
            'booking_code'  => $booking->booking_code,
            'customer_name' => $customerName,
            'court_name'    => $courtName,
            'hub_name'      => $hubName,
            'hub_id'        => $hubId,
            'start_time'    => $booking->start_time->toIso8601String(),
            'message'       => $message,
        ];
    }
}
