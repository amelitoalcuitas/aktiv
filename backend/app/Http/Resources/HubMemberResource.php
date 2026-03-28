<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HubMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->user->id,
            'name'             => $this->user->name,
            'username'         => $this->user->username,
            'avatar_thumb_url' => $this->user->avatar_thumb_url,
        ];
    }
}
