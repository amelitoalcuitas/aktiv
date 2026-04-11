<?php

namespace App\Notifications;

use App\Mail\OpenPlayParticipantCancelled;
use App\Mail\OpenPlayParticipantConfirmed;
use App\Mail\OpenPlayParticipantRejected;
use App\Mail\OpenPlaySessionCancelled;
use App\Mail\OpenPlaySessionStarted;
use App\Mail\OpenPlaySessionUpdated;
use App\Models\OpenPlayParticipant;
use Illuminate\Bus\Queueable;
use Carbon\CarbonInterface;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OpenPlayActivityNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly OpenPlayParticipant $participant,
        private readonly string $activityType,
        private readonly array $context = [],
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

        $emailable = [
            'open_play_participant_joined',
            'open_play_participant_cancelled_by_customer',
            'open_play_receipt_uploaded',
            'open_play_participant_confirmed',
            'open_play_participant_rejected',
            'open_play_participant_cancelled',
            'open_play_session_cancelled',
            'open_play_session_started',
            'open_play_session_updated',
        ];

        if (in_array($this->activityType, $emailable) && ($notifiable->email_notifications_enabled ?? true)) {
            if (! filled($notifiable->email ?? null)) {
                return $channels;
            }

            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $participant = $this->participant;
        $session     = $participant->openPlaySession;
        $booking     = $session->booking;
        $hub         = $booking->court->hub;
        $courtName   = $booking->court->name;
        $frontendUrl = config('app.frontend_url');

        $participantName = $participant->user?->name
            ?? $participant->guest_name
            ?? 'Guest';

        $subject = match ($this->activityType) {
            'open_play_participant_joined' => "New Open Play Participant - {$hub->name}",
            'open_play_participant_cancelled_by_customer' => "Open Play Spot Released - {$hub->name}",
            'open_play_receipt_uploaded' => "New Receipt to Review - {$hub->name}",
            'open_play_participant_confirmed' => "Open Play Spot Confirmed - {$hub->name}",
            'open_play_participant_rejected' => "Open Play Receipt Rejected - {$hub->name}",
            'open_play_participant_cancelled' => "Open Play Spot Released - {$hub->name}",
            'open_play_session_cancelled' => "Open Play Session Cancelled - {$hub->name}",
            'open_play_session_started' => "Open Play Session Started - {$hub->name}",
            'open_play_session_updated' => "Open Play Session Updated - {$hub->name}",
            default => "Open Play Update - {$hub->name}",
        };

        $view = match ($this->activityType) {
            'open_play_participant_joined' => 'emails.open-play-owner-join-notification',
            'open_play_participant_cancelled_by_customer' => 'emails.open-play-owner-participant-cancelled',
            'open_play_receipt_uploaded' => 'emails.open-play-owner-receipt-notification',
            default => null,
        };

        if ($view) {
            return (new MailMessage)
                ->subject($subject)
                ->view($view, compact('participant', 'session', 'booking', 'hub', 'courtName', 'frontendUrl', 'participantName'));
        }

        $viewData = compact('participant', 'session', 'frontendUrl');

        if ($this->activityType === 'open_play_session_updated') {
            $viewData = [
                'participant' => $participant,
                'session' => $session,
                'frontendUrl' => $frontendUrl,
                'originalCourtName' => $this->context['original_court_name'] ?? $session->booking->court->name,
                'originalStartTime' => $this->context['original_start_time'] ?? $session->booking->start_time,
                'originalEndTime' => $this->context['original_end_time'] ?? $session->booking->end_time,
            ];
        }

        if ($this->activityType === 'open_play_participant_confirmed') {
            $view = 'emails.open-play-participant-confirmed';
        } elseif ($this->activityType === 'open_play_participant_rejected') {
            $view = 'emails.open-play-participant-rejected';
        } elseif ($this->activityType === 'open_play_participant_cancelled') {
            $view = 'emails.open-play-participant-cancelled';
        } elseif ($this->activityType === 'open_play_session_cancelled') {
            $view = 'emails.open-play-session-cancelled';
        } elseif ($this->activityType === 'open_play_session_started') {
            $view = 'emails.open-play-session-started';
        } elseif ($this->activityType === 'open_play_session_updated') {
            $view = 'emails.open-play-session-updated';
        }

        return (new MailMessage)
            ->subject($subject)
            ->view($view, $viewData);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $participant = $this->participant;
        $session     = $participant->openPlaySession;
        $booking     = $session->booking;
        $hub         = $booking->court->hub;
        $courtName   = $booking->court->name;
        $sport       = $session->sport ?? 'Open Play';

        $message = match ($this->activityType) {
            'open_play_participant_joined'     => ($participant->user?->name ?? $participant->guest_name ?? 'A participant') . " joined {$sport} on {$courtName}.",
            'open_play_participant_cancelled_by_customer' => ($participant->user?->name ?? $participant->guest_name ?? 'A participant') . " cancelled their {$sport} join on {$courtName}.",
            'open_play_receipt_uploaded'       => ($participant->user?->name ?? $participant->guest_name ?? 'A participant') . " uploaded a receipt for {$sport} on {$courtName}.",
            'open_play_participant_confirmed'  => "Your spot in the {$sport} session at {$hub->name} has been confirmed.",
            'open_play_participant_rejected'   => "Your receipt for {$sport} at {$hub->name} was not accepted. Please re-upload.",
            'open_play_participant_cancelled'  => "Your spot in the {$sport} session at {$hub->name} has been cancelled.",
            'open_play_session_cancelled'      => "{$hub->name} has cancelled the {$sport} open play session.",
            'open_play_session_started'        => "Your {$sport} session at {$hub->name} has started! Head to {$courtName}.",
            'open_play_session_updated'        => "{$hub->name} updated the court or schedule for your {$sport} session.",
            default                            => "Your open play status at {$hub->name} has been updated.",
        };

        return [
            'activity_type' => $this->activityType,
            'item_id'       => $participant->id,
            'session_id'    => $session->id,
            'hub_id'        => $hub->id,
            'hub_name'      => $hub->name,
            'court_name'    => $courtName,
            'start_time'    => $booking->start_time->toIso8601String(),
            'message'       => $message,
            'original_court_name' => $this->context['original_court_name'] ?? null,
            'original_start_time' => $this->formatContextTime($this->context['original_start_time'] ?? null),
            'original_end_time'   => $this->formatContextTime($this->context['original_end_time'] ?? null),
        ];
    }

    private function formatContextTime(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toIso8601String();
        }

        return is_string($value) ? $value : null;
    }
}
