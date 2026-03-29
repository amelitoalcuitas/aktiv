<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGuestBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $court = $this->route('court');
        $allowedPaymentMethods = $court
            ? ($court->hub->settings?->payment_methods ?? ['pay_on_site'])
            : ['pay_on_site', 'digital_bank'];

        return [
            'email'          => ['required', 'email', 'max:255'],
            'otp'            => ['required', 'string', 'size:6'],
            'guest_name'     => ['required', 'string', 'max:255'],
            'guest_phone'    => ['nullable', 'string', 'max:20'],
            'start_time'     => ['required', 'date', 'after:now'],
            'end_time'       => ['required', 'date', 'after:start_time'],
            'session_type'   => ['required', Rule::in(['private', 'open_play'])],
            'payment_method' => ['required', 'string', Rule::in($allowedPaymentMethods)],
            'voucher_code'   => ['nullable', 'string', 'max:12'],
        ];
    }

    public function messages(): array
    {
        return [
'start_time.after'  => 'You cannot book a slot in the past.',
            'end_time.after'    => 'End time must be after the start time.',
        ];
    }
}
