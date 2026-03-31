<?php

namespace App\Services;

use App\Events\BookingSlotUpdated;
use App\Events\NotificationBroadcast;
use App\Mail\BookingConfirmation;
use App\Mail\BookingStatusUpdate;
use App\Mail\GuestBookingVerification;
use App\Mail\OwnerCancelledBookingNotification;
use App\Mail\OwnerBookingNotification;
use App\Mail\WalkInBookingConfirmation;
use App\Models\Booking;
use App\Models\Hub;
use App\Models\User;
use App\Notifications\BookingActivityNotification;
use Illuminate\Support\Facades\Mail;

class BookingNotificationService
{
    /**
     * New booking created (self-booked or guest).
     * Sends confirmation to booker/guest and alert to owner with in-app notification.
     * The booking must have court.hub.owner eager-loaded before calling.
     */
    public function notifyNewBooking(Booking $booking): void
    {
        $hub = $booking->court->hub;

        $recipientEmail = $booking->booked_by
            ? $booking->bookedBy?->email
            : $booking->guest_email;

        if ($recipientEmail) {
            Mail::to($recipientEmail)->queue(
                new BookingConfirmation($booking, $hub, $booking->court->name, $booking->guest_tracking_token)
            );
        }

        $owner = $hub->owner;
        if ($owner) {
            Mail::to($owner->email)->queue(new OwnerBookingNotification($booking, $hub, $booking->court->name));
            $this->sendActivityNotification($owner, $booking, 'booking_created');
        }
    }

    /**
     * Guest or user uploaded a payment receipt.
     * Notifies the hub owner via in-app (+ email if enabled).
     * The booking must have court.hub.owner eager-loaded before calling.
     */
    public function notifyReceiptUploaded(Booking $booking): void
    {
        $owner = $booking->court->hub->owner;
        if ($owner) {
            $this->sendActivityNotification($owner, $booking, 'receipt_uploaded');
        }
    }

    /**
     * Owner confirmed the booking (payment_sent → confirmed).
     * Notifies the registered booker via in-app (+ email if enabled),
     * or queues a direct email for guest bookings.
     * The booking must have court.hub eager-loaded before calling.
     */
    public function notifyBookingConfirmed(Booking $booking): void
    {
        if ($booking->bookedBy) {
            $this->sendActivityNotification($booking->bookedBy, $booking, 'booking_confirmed');
        } elseif ($booking->guest_email) {
            Mail::to($booking->guest_email)->queue(
                new BookingStatusUpdate($booking, $booking->court->hub, 'booking_confirmed')
            );
        }
    }

    /**
     * Owner rejected the receipt (payment_sent → pending_payment).
     * Notifies the registered booker via in-app (+ email if enabled),
     * or queues a direct email for guest bookings.
     * The booking must have court.hub eager-loaded before calling.
     */
    public function notifyBookingRejected(Booking $booking): void
    {
        if ($booking->bookedBy) {
            $this->sendActivityNotification($booking->bookedBy, $booking, 'booking_rejected');
        } elseif ($booking->guest_email) {
            Mail::to($booking->guest_email)->queue(
                new BookingStatusUpdate($booking, $booking->court->hub, 'booking_rejected')
            );
        }
    }

    /**
     * Booking cancelled — either by the owner or by the guest.
     *
     * cancelledBy = 'owner': notifies booker/guest + queues owner record email.
     * cancelledBy = 'guest': queues guest confirmation + notifies owner in-app.
     *
     * The booking must have court.hub.owner eager-loaded before calling.
     */
    public function notifyBookingCancelled(Booking $booking, string $cancelledBy): void
    {
        $hub   = $booking->court->hub;
        $owner = $hub->owner;

        if ($cancelledBy === 'owner') {
            if ($booking->bookedBy) {
                $this->sendActivityNotification($booking->bookedBy, $booking, 'booking_cancelled');
            } elseif ($booking->guest_email) {
                Mail::to($booking->guest_email)->queue(
                    new BookingStatusUpdate($booking, $hub, 'booking_cancelled')
                );
            }

            if ($owner) {
                Mail::to($owner->email)->queue(new OwnerCancelledBookingNotification($booking, $hub));
            }
        } else {
            // Cancelled by guest
            if ($booking->guest_email) {
                Mail::to($booking->guest_email)->queue(
                    new BookingStatusUpdate($booking, $hub, 'booking_cancelled')
                );
            }

            if ($owner) {
                $this->sendActivityNotification($owner, $booking, 'booking_cancelled_by_guest');
            }
        }
    }

    /**
     * Owner updated an existing booking (times/court changed).
     * Queues a direct email to the guest if one is on file.
     * The booking must have court.hub eager-loaded before calling.
     */
    public function notifyBookingUpdated(Booking $booking): void
    {
        if ($booking->guest_email) {
            Mail::to($booking->guest_email)->queue(
                new BookingStatusUpdate($booking, $booking->court->hub, 'booking_updated')
            );
        }
    }

    /**
     * Owner-created walk-in booking.
     * Queues a confirmation email to the guest if an email was provided.
     * The booking must have court.hub eager-loaded before calling.
     */
    public function notifyWalkInBooking(Booking $booking): void
    {
        if ($booking->guest_email) {
            Mail::to($booking->guest_email)->queue(
                new WalkInBookingConfirmation(
                    $booking,
                    $booking->court->hub,
                    $booking->court->name,
                    $booking->guest_tracking_token
                )
            );
        }
    }

    /**
     * Send a 6-digit OTP to a guest's email for booking verification.
     */
    public function notifyGuestVerification(string $email, string $code, string $hubName = ''): void
    {
        Mail::to($email)->queue(new GuestBookingVerification($code, $hubName));
    }

    // ── Private helpers ──────────────────────────────────────────

    /**
     * Persist a database notification for $recipient and broadcast it via Reverb.
     * The booking must have court.hub eager-loaded before calling.
     */
    private function sendActivityNotification(User $recipient, Booking $booking, string $activityType): void
    {
        $notification = new BookingActivityNotification($booking, $activityType);
        $recipient->notifyNow($notification);

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

        broadcast(new BookingSlotUpdated(
            hubId: $booking->court->hub_id,
            courtId: $booking->court_id,
            status: $booking->status,
        ));
    }
}
