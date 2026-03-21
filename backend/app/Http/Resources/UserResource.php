<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'email'             => $this->email,
            'avatar_url'        => $this->avatar_url,
            'phone'             => $this->phone,
            'google_id'         => $this->google_id,
            'role'                    => $this->role->value,
            'email_verified_at'       => $this->email_verified_at,
            'expired_booking_strikes' => $this->expired_booking_strikes ?? 0,
            'booking_banned_until'    => $this->booking_banned_until?->toIso8601String(),
            'created_at'              => $this->created_at,
        ];
    }
}
