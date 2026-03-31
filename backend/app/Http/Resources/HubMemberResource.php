<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HubMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $privacy = $this->user->resolvedPrivacy();
        $hidden  = ($privacy['profile_visible_to'] ?? 'everyone') === 'no_one';

        return [
            'id'               => $this->user->id,
            'name'             => $hidden ? null : (($privacy['show_full_name'] ?? true) ? $this->user->name : ($this->user->username ? '@' . $this->user->username : null)),
            'username'         => $this->user->username,
            'avatar_thumb_url' => $hidden ? null : $this->user->avatar_thumb_url,
            'is_premium'       => (bool) $this->user->is_premium,
        ];
    }
}
