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
use Illuminate\Mail\Mailable;
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

    public function toMail(object $notifiable): Mailable
    {
        $participant = $this->participant;
        $session     = $participant->openPlaySession;

        $mail = match ($this->activityType) {
            'open_play_participant_confirmed'  => new OpenPlayParticipantConfirmed($participant, $session),
            'open_play_participant_rejected'   => new OpenPlayParticipantRejected($participant, $session),
            'open_play_participant_cancelled'  => new OpenPlayParticipantCancelled($participant, $session),
            'open_play_session_cancelled'      => new OpenPlaySessionCancelled($participant, $session),
            'open_play_session_started'        => new OpenPlaySessionStarted($participant, $session),
            'open_play_session_updated'        => new OpenPlaySessionUpdated(
                $participant,
                $session,
                $this->context['original_court_name'] ?? $session->booking->court->name,
                $this->context['original_start_time'] ?? $session->booking->start_time,
                $this->context['original_end_time'] ?? $session->booking->end_time,
            ),
            default                            => new OpenPlayParticipantConfirmed($participant, $session),
        };

        return $mail->to($notifiable->email);
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
