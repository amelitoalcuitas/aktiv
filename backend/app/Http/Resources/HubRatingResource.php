<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HubRatingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'rating'     => $this->rating,
            'comment'    => $this->comment,
            'created_at' => $this->created_at,
            'court_name' => $this->booking?->court?->name,
            'images'     => $this->whenLoaded('images', fn () =>
                $this->images->map(fn ($img) => ['url' => $img->url])->values()
            ),
            'user'       => [
                'id'         => $this->user->id,
                'name'       => $this->censorName($this->user->name),
                'avatar_url' => $this->user->avatar_url,
            ],
        ];
    }

    private function censorName(string $name): string
    {
        $first = explode(' ', $name)[0];
        $len = mb_strlen($first);
        if ($len <= 2) {
            return mb_substr($first, 0, 1) . str_repeat('*', max(1, $len - 1));
        }
        return mb_substr($first, 0, 1) . str_repeat('*', $len - 2) . mb_substr($first, -1);
    }
}
