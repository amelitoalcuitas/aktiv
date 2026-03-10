<?php

namespace App\Http\Requests\Hub;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHubRequest extends FormRequest
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
            'name'            => ['sometimes', 'string', 'max:255'],
            'description'     => ['sometimes', 'nullable', 'string'],
            'city'            => ['sometimes', 'string', 'max:255'],
            'address'         => ['sometimes', 'string', 'max:500'],
            'address_line2'   => ['sometimes', 'nullable', 'string', 'max:500'],
            'landmark'        => ['sometimes', 'nullable', 'string', 'max:500'],
            'zip_code'        => ['sometimes', 'string', 'max:20'],
            'province'        => ['sometimes', 'string', 'max:255'],
            'country'         => ['sometimes', 'string', 'max:255'],
            'lat'             => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lng'             => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'cover_image_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'sports'          => ['sometimes', 'array'],
            'sports.*'        => ['string', 'in:tennis,badminton,basketball,pickleball,volleyball'],
        ];
    }
}
