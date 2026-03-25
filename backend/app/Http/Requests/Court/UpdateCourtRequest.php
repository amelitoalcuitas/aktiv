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
            'name'                     => ['sometimes', 'string', 'max:36'],
            'surface'                  => ['sometimes', 'nullable', 'string', 'max:50'],
            'indoor'                   => ['sometimes', 'nullable', 'boolean'],
            'price_per_hour'           => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'is_active'                => ['sometimes', 'nullable', 'boolean'],
            'sports'                   => ['sometimes', 'array'],
            'sports.*'                 => ['string', 'max:50'],
            'court_image'              => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'remove_court_image'       => ['nullable', 'boolean'],
        ];
    }
}
