<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GuestBookingVerification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly string $hubName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Booking Verification Code – ' . $this->hubName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.guest-booking-verification',
        );
    }
}
