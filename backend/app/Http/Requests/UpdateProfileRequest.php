<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'                        => ['sometimes', 'string', 'max:100'],
            'last_name'                         => ['sometimes', 'string', 'max:100'],
            'username'                          => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
                'alpha_dash',
                Rule::unique('users', 'username')->ignore($this->user()->id),
            ],
            'contact_number'                    => ['sometimes', 'nullable', 'string', 'max:30'],
            'bio'                               => ['sometimes', 'nullable', 'string', 'max:500'],
            'social_links'                      => ['sometimes', 'nullable', 'array'],
            'social_links.facebook'             => ['sometimes', 'nullable', 'string', 'max:255'],
            'social_links.instagram'            => ['sometimes', 'nullable', 'string', 'max:255'],
            'social_links.x'                    => ['sometimes', 'nullable', 'string', 'max:255'],
            'social_links.youtube'              => ['sometimes', 'nullable', 'string', 'max:255'],
            'social_links.threads'              => ['sometimes', 'nullable', 'string', 'max:255'],
            'social_links.other'                => ['sometimes', 'nullable', 'string', 'max:255'],
            'profile_privacy'                      => ['sometimes', 'nullable', 'array'],
            'profile_privacy.show_owned_hubs'      => ['sometimes', 'boolean'],
            'profile_privacy.show_visited_hubs'    => ['sometimes', 'boolean'],
            'profile_privacy.show_leaderboard'     => ['sometimes', 'boolean'],
            'profile_privacy.show_hearts'          => ['sometimes', 'boolean'],
            'profile_privacy.show_tournaments'     => ['sometimes', 'boolean'],
            'profile_privacy.show_open_play'       => ['sometimes', 'boolean'],
            'profile_privacy.show_favorite_sports' => ['sometimes', 'boolean'],
            'hub_display_order'                    => ['sometimes', 'nullable', 'array'],
            'hub_display_order.*'                  => ['uuid'],
        ];
    }
}
