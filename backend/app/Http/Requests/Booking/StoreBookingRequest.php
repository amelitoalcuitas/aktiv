<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $court = $this->route('court');
        $allowedSports = $court ? $court->sports->pluck('sport')->toArray() : [];

        $allowedPaymentMethods = $court
            ? ($court->hub->settings?->payment_methods ?? ['pay_on_site'])
            : ['pay_on_site', 'digital_bank'];

        return [
            'sport' => ['required', 'string', Rule::in($allowedSports)],
            'start_time' => ['required', 'date', 'after:now'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'session_type' => ['required', Rule::in(['private', 'open_play'])],
            'payment_method' => ['required', 'string', Rule::in($allowedPaymentMethods)],
        ];
    }

    public function messages(): array
    {
        return [
            'sport.in' => 'The selected sport is not available for this court.',
            'start_time.after' => 'You cannot book a slot in the past.',
            'end_time.after' => 'End time must be after the start time.',
        ];
    }
}
