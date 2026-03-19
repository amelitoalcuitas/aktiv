<?php

namespace App\Http\Requests\Hub;

use Illuminate\Foundation\Http\FormRequest;

class StoreHubRequest extends FormRequest
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
            'name'            => ['required', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'city'            => ['required', 'string', 'max:255'],
            'address'         => ['required', 'string', 'max:500'],
            'address_line2'   => ['nullable', 'string', 'max:500'],
            'landmark'        => ['nullable', 'string', 'max:500'],
            'zip_code'        => ['required', 'string', 'max:20'],
            'province'        => ['required', 'string', 'max:255'],
            'country'         => ['required', 'string', 'max:255'],
            'lat'             => ['nullable', 'numeric', 'between:-90,90'],
            'lng'             => ['nullable', 'numeric', 'between:-180,180'],
            'cover_image'     => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
            'gallery_images'  => ['nullable', 'array', 'max:10'],
            'gallery_images.*' => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
            'is_active'                 => ['nullable', 'boolean'],
            'sports'                    => ['nullable', 'array'],
            'sports.*'                  => ['string', 'in:tennis,badminton,basketball,pickleball,volleyball'],
            'contact_numbers'           => ['nullable', 'array', 'max:5'],
            'contact_numbers.*.type'    => ['required', 'string', 'in:mobile,landline'],
            'contact_numbers.*.number'  => ['required', 'string', 'max:20'],
            'websites'                  => ['nullable', 'array', 'max:5'],
            'websites.*.url'            => ['required', 'string', 'url', 'max:2048'],
            'operating_hours'                       => ['nullable', 'array', 'max:7'],
            'operating_hours.*.day_of_week'         => ['required', 'integer', 'between:0,6'],
            'operating_hours.*.opens_at'            => ['required_if:operating_hours.*.is_closed,false', 'nullable', 'date_format:H:i'],
            'operating_hours.*.closes_at'           => ['required_if:operating_hours.*.is_closed,false', 'nullable', 'date_format:H:i'],
            'operating_hours.*.is_closed'           => ['nullable', 'boolean'],
        ];
    }
}
