<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpenPlayParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'open_play_session_id' => $this->open_play_session_id,
            'user_id'              => $this->user_id,
            'user'                 => $this->whenLoaded('user', fn () => $this->user ? [
                'id'             => $this->user->id,
                'first_name'     => $this->user->first_name,
                'last_name'      => $this->user->last_name,
                'email'          => $this->user->email,
                'contact_number' => $this->user->contact_number,
                'avatar_url'     => $this->user->avatar_url,
            ] : null),
            'guest_name'           => $this->guest_name,
            'guest_phone'          => $this->guest_phone,
            'guest_email'          => $this->guest_email,
            'guest_tracking_token' => $this->guest_tracking_token,
            'payment_method'       => $this->payment_method,
            'payment_status'       => $this->payment_status,
            'receipt_image_url'    => $this->receipt_image_url,
            'receipt_uploaded_at'  => $this->receipt_uploaded_at?->toIso8601String(),
            'payment_note'         => $this->payment_note,
            'payment_confirmed_by' => $this->payment_confirmed_by,
            'payment_confirmed_at' => $this->payment_confirmed_at?->toIso8601String(),
            'expires_at'           => $this->expires_at?->toIso8601String(),
            'cancelled_by'         => $this->cancelled_by,
            'joined_at'            => $this->joined_at?->toIso8601String(),
            'created_at'           => $this->created_at->toIso8601String(),
        ];
    }
}
