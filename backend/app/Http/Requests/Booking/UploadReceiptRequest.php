<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class UploadReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receipt_image' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:10240', // 10 MB
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'receipt_image.required' => 'Please select a receipt image to upload.',
            'receipt_image.mimes' => 'Receipt must be a JPG, PNG, or WebP image.',
            'receipt_image.max' => 'Receipt image must not exceed 10 MB.',
        ];
    }
}
