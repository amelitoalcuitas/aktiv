<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'booking_code'         => $this->booking_code,
            'sport'                => $this->sport,
            'start_time'           => $this->start_time,
            'end_time'             => $this->end_time,
            'session_type'         => $this->session_type,
            'status'               => $this->status,
            'booking_source'       => $this->booking_source,
            'payment_method'       => $this->payment_method,
            'total_price'          => $this->total_price,
            'original_price'       => $this->original_price,
            'discount_amount'      => $this->discount_amount,
            'applied_promo_title'  => $this->applied_promo_title,
            'receipt_image_url'    => $this->receipt_image_url,
            'receipt_uploaded_at'  => $this->receipt_uploaded_at,
            'payment_note'         => $this->payment_note,
            'expires_at'           => $this->expires_at,
            'cancelled_by'         => $this->cancelled_by,
            'created_at'           => $this->created_at,
            'court' => $this->whenLoaded('court', fn () => [
                'id'   => $this->court->id,
                'name' => $this->court->name,
                'hub'  => $this->court->relationLoaded('hub') ? [
                    'id'              => $this->court->hub->id,
                    'name'            => $this->court->hub->name,
                    'cover_image_url' => $this->court->hub->cover_image_url,
                ] : null,
            ]),
        ];
    }
}
