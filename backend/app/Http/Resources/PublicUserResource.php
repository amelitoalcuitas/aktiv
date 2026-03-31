<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class PublicUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $privacy  = $this->resolvedPrivacy();
        $isOwner  = Auth::guard('sanctum')->id() === $this->id;
        $isPrivate = ($privacy['profile_visible_to'] ?? 'everyone') === 'no_one' && !$isOwner;

        if ($isPrivate) {
            return [
                'id'          => $this->id,
                'username'    => $this->username,
                'first_name'  => $this->first_name,
                'last_name'   => $this->last_name,
                'avatar_url'  => $this->avatar_url,
                'banner_url'  => $this->banner_url,
                'bio'         => $this->bio,
                'is_premium'  => (bool) $this->is_premium,
                'is_hub_owner' => $this->hubs()->exists(),
                'hearts_count' => $this->heartsReceived()->count(),
                'has_hearted'  => false,
                'created_at'  => $this->created_at,
                'is_private'  => true,
                'privacy'     => $privacy,
            ];
        }

        return [
            'is_private' => false,
            'id'             => $this->id,
            'first_name'     => ($privacy['show_full_name'] ?? true) ? $this->first_name : null,
            'last_name'      => ($privacy['show_full_name'] ?? true) ? $this->last_name  : null,
            'username'       => $this->username,
            'avatar_url'     => $this->avatar_url,
            'banner_url'     => $this->banner_url,
            'bio'            => $this->bio,
            'is_premium'     => (bool) $this->is_premium,
            'social_links'   => $this->social_links ?? [],
            'is_hub_owner'   => $this->hubs()->exists(),
            'owned_hubs'     => $privacy['show_owned_hubs']
                ? $this->orderedOwnedHubs()
                    ->filter(fn ($h) => $h->show_on_profile)
                    ->values()
                    ->map(fn ($h) => [
                        'id'              => $h->id,
                        'name'            => $h->name,
                        'description'     => $h->description,
                        'city'            => $h->city,
                        'cover_image_url' => $h->cover_image_url,
                        'rating'          => $h->reviews_count > 0
                            ? round((5 * 3.5 + (float) $h->ratings_avg_rating * $h->reviews_count) / (5 + $h->reviews_count), 1)
                            : null,
                    ])
                : [],
            'joined_hubs'    => $privacy['show_joined_hubs']
                ? $this->joinedHubs()->select(['hubs.id', 'hubs.name', 'hubs.city', 'hubs.cover_image_url'])->get()->map(fn ($h) => [
                    'id'              => $h->id,
                    'name'            => $h->name,
                    'city'            => $h->city,
                    'cover_image_url' => $h->cover_image_url,
                ])
                : [],
            'hearts_count'   => $privacy['show_hearts'] ? $this->heartsReceived()->count() : null,
            'has_hearted'    => ($viewer = Auth::guard('sanctum')->user())
                ? $this->heartsReceived()->where('from_user_id', $viewer->id)->exists()
                : false,
            'privacy'        => $privacy,
            'created_at'     => $this->created_at,
        ];
    }
}
