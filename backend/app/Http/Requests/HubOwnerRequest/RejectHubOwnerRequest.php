<?php

namespace App\Http\Requests\HubOwnerRequest;

use Illuminate\Foundation\Http\FormRequest;

class RejectHubOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
