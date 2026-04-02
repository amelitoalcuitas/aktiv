<?php

namespace App\Http\Requests\OpenPlay;

use Illuminate\Foundation\Http\FormRequest;

class SendGuestVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
        ];
    }
}
