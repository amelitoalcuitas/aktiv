<?php

namespace App\Mail;

use App\Models\OpenPlayParticipant;
use App\Models\OpenPlaySession;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OpenPlaySessionCancelled extends Mailable
{
    use Queueable, SerializesModels;

    public readonly string $frontendUrl;

    public function __construct(
        public readonly OpenPlayParticipant $participant,
        public readonly OpenPlaySession $session,
    ) {
        $this->frontendUrl = config('app.frontend_url');
    }

    public function envelope(): Envelope
    {
        $hubName = $this->session->booking->court->hub->name;

        return new Envelope(
            subject: "{$hubName} Has Cancelled the Open Play Session",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.open-play-session-cancelled',
        );
    }
}
