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
        $allowedSports = $court ? $court->sports->pluck('sport')->toArray() : [];

        return [
            'email'        => ['required', 'email', 'max:255'],
            'otp'          => ['required', 'string', 'size:6'],
            'guest_name'   => ['required', 'string', 'max:255'],
            'guest_phone'  => ['nullable', 'string', 'max:20'],
            'sport'        => ['required', 'string', Rule::in($allowedSports)],
            'start_time'   => ['required', 'date', 'after:now'],
            'end_time'     => ['required', 'date', 'after:start_time'],
            'session_type' => ['required', Rule::in(['private', 'open_play'])],
        ];
    }

    public function messages(): array
    {
        return [
            'sport.in'          => 'The selected sport is not available for this court.',
            'start_time.after'  => 'You cannot book a slot in the past.',
            'end_time.after'    => 'End time must be after the start time.',
        ];
    }
}
