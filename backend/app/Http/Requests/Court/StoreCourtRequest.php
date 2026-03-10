<?php

namespace App\Http\Requests\Court;

use Illuminate\Foundation\Http\FormRequest;

class StoreCourtRequest extends FormRequest
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
            'name'           => ['required', 'string', 'max:255'],
            'surface'        => ['nullable', 'string', 'in:hardcourt,clay,synthetic,grass,concrete,wood'],
            'indoor'         => ['nullable', 'boolean'],
            'price_per_hour' => ['nullable', 'numeric', 'min:0'],
            'max_players'    => ['nullable', 'integer', 'min:1'],
            'is_active'      => ['nullable', 'boolean'],
            'sports'         => ['nullable', 'array'],
            'sports.*'       => ['string', 'in:tennis,badminton,basketball,pickleball,volleyball'],
        ];
    }
}
