<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $latestHubOwnerRequest = $this->hubOwnerRequests()->latest()->first();

        return [
            'id'                      => $this->id,
            'first_name'              => $this->first_name,
            'last_name'               => $this->last_name,
            'username'                => $this->username,
            'email'                   => $this->email,
            'avatar_url'              => $this->avatar_url,
            'avatar_thumb_url'        => $this->avatar_thumb_url,
            'contact_number'          => $this->contact_number,
            'country'                 => $this->country,
            'province'                => $this->province,
            'city'                    => $this->city,
            'google_id'               => $this->google_id,
            'role'                    => $this->role->value,
            'is_premium'              => (bool) $this->is_premium,
            'email_verified_at'       => $this->email_verified_at,
            'expired_booking_strikes' => $this->expired_booking_strikes ?? 0,
            'booking_banned_until'    => $this->booking_banned_until?->toIso8601String(),
            'deletion_scheduled_at'   => $this->deletion_scheduled_at?->toIso8601String(),
            'hub_owner_request_status' => $latestHubOwnerRequest?->status->value ?? 'none',
            'created_at'              => $this->created_at,
        ];
    }
}
