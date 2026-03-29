<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class PreviewVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'voucher_code' => ['required', 'string', 'max:12'],
            'guest_email' => ['nullable', 'email', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.court_id' => ['required', 'uuid'],
            'items.*.start_time' => ['required', 'date'],
            'items.*.end_time' => ['required', 'date'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                foreach ($this->input('items', []) as $index => $item) {
                    if (
                        isset($item['start_time'], $item['end_time'])
                        && strtotime((string) $item['end_time']) <= strtotime((string) $item['start_time'])
                    ) {
                        $validator->errors()->add("items.{$index}.end_time", 'End time must be after the start time.');
                    }
                }
            },
        ];
    }
}
