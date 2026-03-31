<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class CompleteGoogleSignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pending_token' => ['required', 'string', 'max:255'],
            'country'       => ['required', 'string', 'max:255'],
            'province'      => ['required', 'string', 'max:255'],
            'city'          => ['required', 'string', 'max:255'],
        ];
    }
}
