<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
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
        return ['database'];
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
            'booking_created'    => "{$customerName} made a new booking on {$courtName}.",
            'receipt_uploaded'   => "{$customerName} uploaded a payment receipt for their booking on {$courtName}.",
            'booking_confirmed'  => "Your booking on {$courtName} at {$hubName} has been confirmed.",
            'booking_rejected'   => "Your booking on {$courtName} at {$hubName} was rejected. Please re-upload your receipt.",
            'booking_cancelled'  => "Your booking on {$courtName} at {$hubName} has been cancelled.",
            default              => "Your booking on {$courtName} has been updated.",
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
