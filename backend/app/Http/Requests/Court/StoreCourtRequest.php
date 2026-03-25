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
            'name'                     => ['required', 'string', 'max:36'],
            'surface'                  => ['nullable', 'string', 'max:50'],
            'indoor'                   => ['nullable', 'boolean'],
            'price_per_hour'           => ['nullable', 'numeric', 'min:0'],
            'is_active'                => ['nullable', 'boolean'],
            'sports'                   => ['nullable', 'array'],
            'sports.*'                 => ['string', 'max:50'],
            'court_image'              => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }
}
