<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'first_name'     => ['required', 'string', 'max:100'],
            'last_name'      => ['required', 'string', 'max:100'],
            'email'          => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'country'        => ['required', 'string', 'max:255'],
            'province'       => ['required', 'string', 'max:255'],
            'city'           => ['required', 'string', 'max:255'],
            'role'           => ['required', 'in:user,admin'],
            'contact_number' => ['nullable', 'string', 'max:20'],
        ];
    }
}
