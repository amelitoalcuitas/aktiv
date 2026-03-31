<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'     => ['required', 'string', 'max:100'],
            'last_name'      => ['required', 'string', 'max:100'],
            'email'          => ['required', 'email', 'max:255', 'unique:users,email'],
            'country'        => ['required', 'string', 'max:255'],
            'province'       => ['required', 'string', 'max:255'],
            'city'           => ['required', 'string', 'max:255'],
            'role'           => ['nullable', 'in:user,owner'],
            'contact_number' => ['nullable', 'string', 'max:20'],
        ];
    }
}
