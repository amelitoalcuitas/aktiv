<?php

namespace App\Mail;

use App\Models\HubOwnerRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class HubOwnerApplicationRejected extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly HubOwnerRequest $hubOwnerRequest,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your hub owner application was reviewed',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.hub-owner-application-rejected',
        );
    }
}
