<?php

namespace App\Http\Requests\Court;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCourtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name'                     => ['sometimes', 'string', 'max:255'],
            'surface'                  => ['sometimes', 'nullable', 'string', 'in:hardcourt,clay,synthetic,grass,concrete,wood'],
            'indoor'                   => ['sometimes', 'nullable', 'boolean'],
            'price_per_hour'           => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'open_play_price_per_head' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'max_players'              => ['sometimes', 'nullable', 'integer', 'min:1'],
            'is_active'                => ['sometimes', 'nullable', 'boolean'],
            'sports'                   => ['sometimes', 'array'],
            'sports.*'                 => ['string', 'in:tennis,badminton,basketball,pickleball,volleyball'],
        ];
    }
}
