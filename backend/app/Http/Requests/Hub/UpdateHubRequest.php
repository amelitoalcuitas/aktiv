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
            'cover_image'     => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
            'gallery_images'  => ['sometimes', 'array', 'max:10'],
            'gallery_images.*' => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
            'remove_gallery_image_ids' => ['sometimes', 'array'],
            'remove_gallery_image_ids.*' => ['integer', 'exists:hub_images,id'],
            'is_active'                 => ['sometimes', 'nullable', 'boolean'],
            'sports'                    => ['sometimes', 'array'],
            'sports.*'                  => ['string', 'in:tennis,badminton,basketball,pickleball,volleyball'],
            'contact_numbers'           => ['sometimes', 'nullable', 'array', 'max:5'],
            'contact_numbers.*.type'    => ['required', 'string', 'in:mobile,landline'],
            'contact_numbers.*.number'  => ['required', 'string', 'max:20'],
        ];
    }
}
