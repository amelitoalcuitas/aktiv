<?php

namespace App\Http\Requests\OpenPlay;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JoinOpenPlaySessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isGuest = ! $this->user('sanctum');
        $session = $this->route('session');
        $allowedPaymentMethods = $session?->booking?->court?->hub?->settings?->payment_methods ?? ['pay_on_site'];

        return [
            'payment_method' => ['required', Rule::in($allowedPaymentMethods)],
            'guest_name'     => [$isGuest ? 'required' : 'nullable', 'string', 'max:100'],
            'guest_phone'    => [$isGuest ? 'required' : 'nullable', 'string', 'max:30'],
            'guest_email'    => [$isGuest ? 'required' : 'nullable', 'email', 'max:255'],
            'otp'            => [$isGuest ? 'required' : 'nullable', 'string', 'size:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'guest_name.required'  => 'Your name is required to join as a guest.',
            'guest_phone.required' => 'Your phone number is required to join as a guest.',
            'guest_email.required' => 'Your email address is required to join as a guest.',
            'otp.required'         => 'Enter the verification code sent to your email.',
        ];
    }
}
