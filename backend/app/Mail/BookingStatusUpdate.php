<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Hub;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingStatusUpdate extends Mailable
{
    use Queueable, SerializesModels;

    public readonly string $courtName;
    public readonly string $frontendUrl;

    public function __construct(
        public readonly Booking $booking,
        public readonly Hub $hub,
        public readonly string $activityType,
    ) {
        $this->courtName   = $booking->court->name;
        $this->frontendUrl = config('app.frontend_url');
    }

    public function envelope(): Envelope
    {
        $subject = match ($this->activityType) {
            'booking_confirmed' => 'Booking Confirmed – ' . $this->booking->booking_code,
            'booking_rejected'  => 'Receipt Rejected – ' . $this->booking->booking_code,
            'booking_cancelled' => 'Booking Cancelled – ' . $this->booking->booking_code,
            'booking_updated'   => 'Booking Updated – ' . $this->booking->booking_code,
            default             => 'Booking Update – ' . $this->booking->booking_code,
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-status-update',
        );
    }
}
