<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $privacy = $this->resolvedPrivacy();

        return [
            'id'             => $this->id,
            'first_name'     => $this->first_name,
            'last_name'      => $this->last_name,
            'username'       => $this->username,
            'avatar_url'     => $this->avatar_url,
            'banner_url'     => $this->banner_url,
            'bio'            => $this->bio,
            'social_links'   => $this->social_links ?? [],
            'is_hub_owner'   => $this->hubs()->exists(),
            'hearts_count'   => $privacy['show_hearts'] ? $this->heartsReceived()->count() : null,
            'has_hearted'    => $request->user()
                ? $this->heartsReceived()->where('from_user_id', $request->user()->id)->exists()
                : false,
            'privacy'        => $privacy,
            'created_at'     => $this->created_at,
        ];
    }
}
