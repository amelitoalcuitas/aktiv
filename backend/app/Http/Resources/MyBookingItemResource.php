<?php

namespace App\Http\Resources;

use App\Models\Booking;
use App\Models\OpenPlayParticipant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MyBookingItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof OpenPlayParticipant) {
            $session = $this->openPlaySession;
            $booking = $session?->booking;
            $court = $booking?->court;
            $hub = $court?->hub;

            return [
                'id'                  => $this->id,
                'entry_type'          => 'open_play_participant',
                'participant_id'      => $this->id,
                'booking_id'          => $booking?->id,
                'session_id'          => $session?->id,
                'booking_code'        => null,
                'sport'               => $session?->sport ?? $booking?->sport,
                'start_time'          => $booking?->start_time?->toIso8601String(),
                'end_time'            => $booking?->end_time?->toIso8601String(),
                'session_type'        => 'open_play',
                'status'              => $this->payment_status,
                'booking_source'      => $booking?->booking_source,
                'payment_method'      => $this->payment_method,
                'total_price'         => null,
                'price_per_player'    => $session?->price_per_player,
                'original_price'      => null,
                'discount_amount'     => null,
                'applied_promo_title' => null,
                'receipt_image_url'   => $this->receipt_image_url,
                'receipt_uploaded_at' => $this->receipt_uploaded_at?->toIso8601String(),
                'payment_note'        => $this->payment_note,
                'expires_at'          => $this->expires_at?->toIso8601String(),
                'cancelled_by'        => $this->cancelled_by,
                'participants_count'  => $session?->participants_count,
                'max_players'         => $session?->max_players,
                'is_open_play_join'   => true,
                'created_at'          => $this->created_at->toIso8601String(),
                'court'               => $court ? [
                    'id'   => $court->id,
                    'name' => $court->name,
                    'hub'  => $hub ? [
                        'id'              => $hub->id,
                        'username'        => $hub->username,
                        'name'            => $hub->name,
                        'cover_image_url' => $hub->cover_image_url,
                    ] : null,
                ] : null,
            ];
        }

        /** @var Booking $booking */
        $booking = $this->resource;
        $court = $booking->court;
        $hub = $court?->hub;

        return [
            'id'                  => $booking->id,
            'entry_type'          => 'booking',
            'participant_id'      => null,
            'booking_id'          => $booking->id,
            'session_id'          => null,
            'booking_code'        => $booking->booking_code,
            'sport'               => $booking->sport,
            'start_time'          => $booking->start_time?->toIso8601String(),
            'end_time'            => $booking->end_time?->toIso8601String(),
            'session_type'        => $booking->session_type,
            'status'              => $booking->status,
            'booking_source'      => $booking->booking_source,
            'payment_method'      => $booking->payment_method,
            'total_price'         => $booking->total_price,
            'price_per_player'    => null,
            'original_price'      => $booking->original_price,
            'discount_amount'     => $booking->discount_amount,
            'applied_promo_title' => $booking->applied_promo_title,
            'receipt_image_url'   => $booking->receipt_image_url,
            'receipt_uploaded_at' => $booking->receipt_uploaded_at?->toIso8601String(),
            'payment_note'        => $booking->payment_note,
            'expires_at'          => $booking->expires_at?->toIso8601String(),
            'cancelled_by'        => $booking->cancelled_by,
            'participants_count'  => null,
            'max_players'         => null,
            'is_open_play_join'   => false,
            'created_at'          => $booking->created_at->toIso8601String(),
            'court'               => $court ? [
                'id'   => $court->id,
                'name' => $court->name,
                'hub'  => $hub ? [
                    'id'              => $hub->id,
                    'username'        => $hub->username,
                    'name'            => $hub->name,
                    'cover_image_url' => $hub->cover_image_url,
                ] : null,
            ] : null,
        ];
    }
}
