<?php

namespace App\Http\Requests\HubEvent;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHubEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ownership enforced in controller
    }

    public function rules(): array
    {
        return [
            'title'            => ['sometimes', 'string', 'max:100'],
            'description'      => ['nullable', 'string', 'max:500'],
            'event_type'       => ['sometimes', 'in:closure,promo,announcement'],
            'date_from'        => ['sometimes', 'date_format:Y-m-d'],
            'date_to'          => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'time_from'        => ['nullable', 'date_format:H:i'],
            'time_to'          => ['nullable', 'date_format:H:i', 'after:time_from'],
            'discount_type'              => ['nullable', 'in:percent,flat'],
            'discount_value'             => ['nullable', 'numeric', 'min:0'],
            'affected_courts'            => ['nullable', 'array'],
            'affected_courts.*'          => ['uuid'],
            'court_discounts'            => ['nullable', 'array'],
            'court_discounts.*.court_id'      => ['required', 'uuid'],
            'court_discounts.*.discount_type' => ['required', 'in:percent,flat'],
            'court_discounts.*.discount_value'=> ['required', 'numeric', 'min:0'],
            'is_active'                  => ['boolean'],
        ];
    }
}
