<?php

namespace App\Http\Requests\HubOwnerRequest;

use Illuminate\Foundation\Http\FormRequest;
use Closure;

class StoreHubOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hub_name' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'message' => [
                'required',
                'string',
                'max:2000',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $normalizedValue = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
                    $normalizedLength = mb_strlen($normalizedValue);

                    if ($normalizedLength < 50) {
                        $fail('The message field must be at least 50 characters without using repeated spaces to pad it.');
                    }
                },
            ],
        ];
    }
}
