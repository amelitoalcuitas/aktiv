<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class HubMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $privacy = $this->user->resolvedPrivacy();
        $hidden  = ($privacy['profile_visible_to'] ?? 'everyone') === 'no_one';

        if ($hidden) {
            return [
                'id'               => $this->user->id,
                'name'             => $this->maskedName(),
                'username'         => null,
                'avatar_thumb_url' => null,
                'is_premium'       => (bool) $this->user->is_premium,
                'is_private'       => true,
            ];
        }

        return [
            'id'               => $this->user->id,
            'name'             => ($privacy['show_full_name'] ?? true) ? $this->user->name : ($this->user->username ? '@' . $this->user->username : null),
            'username'         => $this->user->username,
            'avatar_thumb_url' => $this->user->avatar_thumb_url ?? $this->user->avatar_url,
            'is_premium'       => (bool) $this->user->is_premium,
            'is_private'       => false,
        ];
    }

    private function maskedName(): string
    {
        $name = trim((string) $this->user->name);

        if ($name !== '') {
            return $this->maskValue($name);
        }

        if ($this->user->username) {
            return $this->maskValue('@' . $this->user->username);
        }

        return '*****';
    }

    private function maskValue(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return '*****';
        }

        $firstCharacter = Str::substr($trimmed, 0, 1);
        $length = Str::length($trimmed);

        return $firstCharacter . str_repeat('*', max(1, $length - 1));
    }
}
