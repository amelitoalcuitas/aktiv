<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'first_name'              => $this->first_name,
            'last_name'               => $this->last_name,
            'username'                => $this->username,
            'username_changed_at'     => $this->username_changed_at?->toIso8601String(),
            'name_changed_at'         => $this->name_changed_at?->toIso8601String(),
            'email'                   => $this->email,
            'avatar_url'              => $this->avatar_url,
            'avatar_thumb_url'        => $this->avatar_thumb_url,
            'banner_url'              => $this->banner_url,
            'contact_number'          => $this->contact_number,
            'bio'                     => $this->bio,
            'social_links'            => $this->social_links ?? [],
            'profile_privacy'         => $this->resolvedPrivacy(),
            'google_id'               => $this->google_id,
            'role'                    => $this->role->value,
            'email_verified_at'       => $this->email_verified_at,
            'is_hub_owner'            => $this->hubs()->exists(),
            'hearts_count'            => $this->heartsReceived()->count(),
            'created_at'              => $this->created_at,
            'expired_booking_strikes' => $this->expired_booking_strikes ?? 0,
            'booking_banned_until'    => $this->booking_banned_until?->toIso8601String(),
        ];
    }
}
