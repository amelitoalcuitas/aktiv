<?php

namespace App\Http\Controllers\Concerns;

use App\Events\NotificationBroadcast;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingActivityNotification;

trait SendsBookingNotification
{
    /**
     * Persist a database notification for $recipient and broadcast it via Reverb.
     * The booking must have court and court.hub eager-loaded before calling this.
     */
    private function notifyBookingActivity(User $recipient, Booking $booking, string $activityType): void
    {
        $notification = new BookingActivityNotification($booking, $activityType);
        $recipient->notifyNow($notification);

        // Retrieve the just-created DB notification to get its ID and formatted data
        $dbNotification = $recipient->notifications()->latest()->first();

        if ($dbNotification) {
            broadcast(new NotificationBroadcast($recipient, [
                'id'            => $dbNotification->id,
                'activity_type' => $dbNotification->data['activity_type'] ?? $activityType,
                'data'          => $dbNotification->data,
                'read_at'       => null,
                'created_at'    => $dbNotification->created_at->toIso8601String(),
            ]));
        }
    }
}
