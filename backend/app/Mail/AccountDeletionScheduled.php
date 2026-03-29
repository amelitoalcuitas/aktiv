<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountDeletionScheduled extends Mailable
{
    use Queueable, SerializesModels;

    public readonly string $frontendUrl;

    public function __construct(
        public readonly User $user,
    ) {
        $this->frontendUrl = config('app.frontend_url');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Aktiv account is scheduled for deletion',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account-deletion-scheduled',
        );
    }
}
