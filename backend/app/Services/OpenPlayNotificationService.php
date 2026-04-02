<?php

namespace App\Services;

use App\Events\BookingSlotUpdated;
use App\Events\NotificationBroadcast;
use App\Mail\OpenPlayJoinConfirmation;
use App\Mail\OpenPlaySessionStarted;
use App\Mail\OpenPlayOwnerReceiptNotification;
use App\Mail\OpenPlayParticipantCancelled;
use App\Mail\OpenPlayParticipantConfirmed;
use App\Mail\OpenPlayParticipantRejected;
use App\Mail\OpenPlayPaymentPending;
use App\Mail\OpenPlaySessionCancelled;
use App\Models\OpenPlayParticipant;
use App\Models\OpenPlaySession;
use App\Models\User;
use App\Notifications\OpenPlayActivityNotification;
use Illuminate\Support\Facades\Mail;

class OpenPlayNotificationService
{
    /**
     * Participant joined.
     * Free or pay_on_site → join confirmation email.
     * digital_bank → payment pending email.
     * The session must have booking.court.hub eager-loaded before calling.
     */
    public function notifyParticipantJoined(OpenPlayParticipant $participant, OpenPlaySession $session): void
    {
        $recipientEmail = $participant->user?->email ?? $participant->guest_email;

        if (! $recipientEmail) {
            return;
        }

        $isFreeOrOnSite = (float) $session->price_per_player === 0.0
            || $participant->payment_method === 'pay_on_site';

        $mail = $isFreeOrOnSite
            ? new OpenPlayJoinConfirmation($participant, $session)
            : new OpenPlayPaymentPending($participant, $session);

        Mail::to($recipientEmail)->queue($mail);
    }

    /**
     * Participant uploaded a receipt.
     * Notifies the hub owner via in-app notification (+ email if enabled).
     * The session must have booking.court.hub.owner eager-loaded before calling.
     */
    public function notifyReceiptUploaded(OpenPlayParticipant $participant, OpenPlaySession $session): void
    {
        $owner = $session->booking->court->hub->owner;

        if (! $owner) {
            return;
        }

        $this->sendActivityNotification($owner, $participant, $session, 'open_play_receipt_uploaded');

        if ($owner->email_notifications_enabled ?? true) {
            Mail::to($owner->email)->queue(new OpenPlayOwnerReceiptNotification($participant, $session));
        }
    }

    /**
     * Owner confirmed a participant's payment.
     * Authenticated user: in-app + email notification.
     * Guest: direct email.
     * The session must have booking.court.hub eager-loaded before calling.
     */
    public function notifyParticipantConfirmed(OpenPlayParticipant $participant, OpenPlaySession $session): void
    {
        if ($participant->user_id && $participant->user) {
            $this->sendActivityNotification($participant->user, $participant, $session, 'open_play_participant_confirmed');
        } elseif ($participant->guest_email) {
            Mail::to($participant->guest_email)->queue(new OpenPlayParticipantConfirmed($participant, $session));
        }
    }

    /**
     * Owner rejected a participant's receipt.
     * Authenticated user: in-app + email notification.
     * Guest: direct email.
     * The session must have booking.court.hub eager-loaded before calling.
     */
    public function notifyParticipantRejected(OpenPlayParticipant $participant, OpenPlaySession $session): void
    {
        if ($participant->user_id && $participant->user) {
            $this->sendActivityNotification($participant->user, $participant, $session, 'open_play_participant_rejected');
        } elseif ($participant->guest_email) {
            Mail::to($participant->guest_email)->queue(new OpenPlayParticipantRejected($participant, $session));
        }
    }

    /**
     * Participant was cancelled (by owner or system).
     * Authenticated user: in-app + email notification.
     * Guest: direct email.
     * If cancelled by system after payment_sent, also notifies owner.
     * The session must have booking.court.hub.owner eager-loaded before calling.
     */
    public function notifyParticipantCancelled(OpenPlayParticipant $participant, OpenPlaySession $session, string $cancelledBy): void
    {
        if ($participant->user_id && $participant->user) {
            $this->sendActivityNotification($participant->user, $participant, $session, 'open_play_participant_cancelled');
        } elseif ($participant->guest_email) {
            Mail::to($participant->guest_email)->queue(new OpenPlayParticipantCancelled($participant, $session));
        }
    }

    /**
     * Session has started.
     * Notifies all confirmed participants.
     * The session must have booking.court.hub eager-loaded before calling.
     */
    public function notifySessionStarted(OpenPlaySession $session): void
    {
        $participants = $session->participants()
            ->where('payment_status', 'confirmed')
            ->with('user')
            ->get();

        foreach ($participants as $participant) {
            $participant->setRelation('openPlaySession', $session);

            if ($participant->user_id && $participant->user) {
                $this->sendActivityNotification($participant->user, $participant, $session, 'open_play_session_started');
            } elseif ($participant->guest_email) {
                Mail::to($participant->guest_email)->queue(new OpenPlaySessionStarted($participant, $session));
            }
        }
    }

    /**
     * Owner cancelled the entire session.
     * Notifies all non-cancelled participants.
     * The session must have booking.court.hub eager-loaded and participants.user eager-loaded before calling.
     */
    public function notifySessionCancelled(OpenPlaySession $session): void
    {
        $participants = $session->participants()
            ->whereNotIn('payment_status', ['cancelled'])
            ->with('user')
            ->get();

        foreach ($participants as $participant) {
            if ($participant->user_id && $participant->user) {
                $this->sendActivityNotification($participant->user, $participant, $session, 'open_play_session_cancelled');
            } elseif ($participant->guest_email) {
                Mail::to($participant->guest_email)->queue(new OpenPlaySessionCancelled($participant, $session));
            }
        }
    }

    // ── Private helpers ──────────────────────────────────────────

    /**
     * Persist a database notification for $recipient and broadcast it via Reverb.
     * The session must have booking.court.hub eager-loaded before calling.
     */
    private function sendActivityNotification(
        User $recipient,
        OpenPlayParticipant $participant,
        OpenPlaySession $session,
        string $activityType
    ): void {
        $notification = new OpenPlayActivityNotification($participant, $activityType);
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

        $booking = $session->booking;

        broadcast(new BookingSlotUpdated(
            hubId: $booking->court->hub_id,
            courtId: $booking->court_id,
            status: $booking->status,
        ));
    }
}
