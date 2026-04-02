<?php

namespace App\Http\Requests\Hub;

use App\Models\Hub;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHubRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('username')) {
            $this->merge([
                'username' => Hub::normalizeUsername($this->input('username')),
            ]);
        }
    }

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
            'username'        => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:' . Hub::USERNAME_REGEX,
                Rule::unique('hubs', 'username'),
            ],
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
            'require_account_to_book'   => ['sometimes', 'nullable', 'boolean'],
            'payment_methods'           => ['sometimes', 'array', 'min:1'],
            'payment_methods.*'         => ['string', 'in:pay_on_site,digital_bank'],
            'payment_qr_image'          => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'digital_bank_name'         => ['nullable', 'string', 'max:255'],
            'digital_bank_account'      => ['nullable', 'string', 'max:255'],
            'sports'                    => ['nullable', 'array'],
            'sports.*'                  => ['string', 'in:tennis,badminton,basketball,pickleball,volleyball'],
            'contact_numbers'           => ['nullable', 'array', 'max:5'],
            'contact_numbers.*.type'    => ['required', 'string', 'in:mobile,landline'],
            'contact_numbers.*.number'  => ['required', 'string', 'max:20'],
            'websites'                  => ['nullable', 'array', 'max:5'],
            'websites.*.platform'       => ['required', 'string', 'in:facebook,instagram,x,youtube,threads,other'],
            'websites.*.url'            => ['required', 'string', 'url', 'max:2048'],
            'operating_hours'                       => ['nullable', 'array', 'max:7'],
            'operating_hours.*.day_of_week'         => ['required', 'integer', 'between:0,6'],
            'operating_hours.*.opens_at'            => ['required_if:operating_hours.*.is_closed,false', 'nullable', 'date_format:H:i'],
            'operating_hours.*.closes_at'           => ['required_if:operating_hours.*.is_closed,false', 'nullable', 'date_format:H:i'],
            'operating_hours.*.is_closed'           => ['nullable', 'boolean'],
        ];
    }
}
