<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WalkInBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'court_id' => ['required', 'integer', 'exists:courts,id'],
'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'session_type' => ['sometimes', Rule::in(['private', 'open_play'])],
            // Either a registered user or guest fields — not both
            'booked_by' => ['nullable', 'integer', 'exists:users,id'],
            'guest_name' => ['required_without:booked_by', 'nullable', 'string', 'max:100'],
            'guest_phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_time.after' => 'End time must be after start time.',
            'guest_name.required_without' => 'Guest name is required when no registered user is selected.',
        ];
    }
}
