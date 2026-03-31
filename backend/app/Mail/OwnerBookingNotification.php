<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Hub;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OwnerBookingNotification extends Mailable
{
    use Queueable, SerializesModels;

    public readonly string $frontendUrl;

    public function __construct(
        public readonly Booking $booking,
        public readonly Hub $hub,
        public readonly string $courtName,
    ) {
        $this->frontendUrl = config('app.frontend_url');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Booking at ' . $this->hub->name . ' – ' . $this->booking->booking_code,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.owner-booking-notification',
        );
    }
}
