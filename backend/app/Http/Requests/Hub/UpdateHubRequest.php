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
            'remove_gallery_image_ids.*' => ['uuid', 'exists:hub_images,id'],
            'is_active'                 => ['sometimes', 'nullable', 'boolean'],
            'require_account_to_book'   => ['sometimes', 'nullable', 'boolean'],
            'guest_booking_limit'       => ['sometimes', 'integer', 'min:1', 'max:10'],
            'guest_max_hours'           => ['sometimes', 'integer', 'min:1', 'max:12'],
            'payment_methods'           => ['sometimes', 'array', 'min:1'],
            'payment_methods.*'         => ['string', 'in:pay_on_site,digital_bank'],
            'payment_qr_image'          => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'digital_bank_name'         => ['nullable', 'string', 'max:255'],
            'digital_bank_account'      => ['nullable', 'string', 'max:255'],
            'remove_payment_qr'         => ['sometimes', 'boolean'],
            'sports'                    => ['sometimes', 'array'],
            'sports.*'                  => ['string', 'in:tennis,badminton,basketball,pickleball,volleyball'],
            'contact_numbers'           => ['sometimes', 'nullable', 'array', 'max:5'],
            'contact_numbers.*.type'    => ['required', 'string', 'in:mobile,landline'],
            'contact_numbers.*.number'  => ['required', 'string', 'max:20'],
            'websites'                  => ['sometimes', 'nullable', 'array', 'max:5'],
            'websites.*.platform'       => ['required', 'string', 'in:facebook,instagram,x,youtube,threads,other'],
            'websites.*.url'            => ['required', 'string', 'url', 'max:2048'],
            'operating_hours'                       => ['sometimes', 'nullable', 'array', 'max:7'],
            'operating_hours.*.day_of_week'         => ['required', 'integer', 'between:0,6'],
            'operating_hours.*.opens_at'            => ['required_if:operating_hours.*.is_closed,false', 'nullable', 'date_format:H:i'],
            'operating_hours.*.closes_at'           => ['required_if:operating_hours.*.is_closed,false', 'nullable', 'date_format:H:i'],
            'operating_hours.*.is_closed'           => ['sometimes', 'boolean'],
        ];
    }
}
