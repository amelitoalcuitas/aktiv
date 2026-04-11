<?php

namespace App\Http\Requests\HubEvent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHubEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ownership enforced in controller
    }

    public function rules(): array
    {
        $isPromoWithoutCourtDiscounts = $this->input('event_type') === 'promo'
            && empty($this->input('court_discounts'));
        $isVoucher = $this->input('event_type') === 'voucher';
        $showAnnouncement = $this->boolean('show_announcement');
        $limitTotalUses = $this->boolean('limit_total_uses');
        $limitPerUserUses = $this->boolean('limit_per_user_uses');

        return [
            'title'            => ['required', 'string', 'max:100'],
            'description'      => array_filter([
                'nullable',
                'string',
                'max:500',
            ]),
            'event_type'       => ['required', 'in:closure,promo,announcement,voucher'],
            'start_time'       => ['required', 'date'],
            'end_time'         => ['required', 'date', 'after:start_time'],
            'discount_type'    => array_filter(['nullable', $isPromoWithoutCourtDiscounts || $isVoucher ? 'required' : null, 'in:percent,flat']),
            'discount_value'   => array_filter(['nullable', $isPromoWithoutCourtDiscounts || $isVoucher ? 'required' : null, 'numeric', 'min:0']),
            'voucher_code'     => array_filter([
                'nullable',
                $isVoucher ? 'required' : null,
                'string',
                'size:12',
                'regex:/^[A-Z0-9]+$/',
                Rule::unique('hub_events', 'voucher_code')->where(
                    fn ($query) => $query->where('hub_id', $this->route('hub')->id)
                ),
            ]),
            'show_announcement' => ['sometimes', 'boolean'],
            'limit_total_uses' => ['sometimes', 'boolean'],
            'max_total_uses' => array_filter([
                'nullable',
                $isVoucher && $limitTotalUses ? 'required' : null,
                $isVoucher ? 'integer' : null,
                $isVoucher ? 'min:1' : null,
            ]),
            'limit_per_user_uses' => ['sometimes', 'boolean'],
            'max_uses_per_user' => array_filter([
                'nullable',
                $isVoucher && $limitPerUserUses ? 'required' : null,
                $isVoucher ? 'integer' : null,
                $isVoucher ? 'min:1' : null,
            ]),
            'affected_courts'            => ['nullable', 'array'],
            'affected_courts.*'          => ['uuid'],
            'court_discounts'            => array_filter([$isVoucher ? 'prohibited' : 'nullable', $isVoucher ? null : 'array']),
            'court_discounts.*.court_id'      => ['required', 'uuid'],
            'court_discounts.*.discount_type' => ['required', 'in:percent,flat'],
            'court_discounts.*.discount_value'=> ['required', 'numeric', 'min:0'],
            'is_active'                  => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $eventType = $this->input('event_type');
        $showAnnouncement = $this->boolean('show_announcement');
        $voucherCode = $this->input('voucher_code');

        $this->merge([
            'voucher_code' => is_string($voucherCode) ? strtoupper(trim($voucherCode)) : $voucherCode,
            'show_announcement' => $eventType === 'voucher' ? $showAnnouncement : true,
            'limit_total_uses' => $eventType === 'voucher' ? $this->boolean('limit_total_uses') : false,
            'max_total_uses' => $eventType === 'voucher' && $this->boolean('limit_total_uses')
                ? $this->input('max_total_uses')
                : null,
            'limit_per_user_uses' => $eventType === 'voucher' ? $this->boolean('limit_per_user_uses') : false,
            'max_uses_per_user' => $eventType === 'voucher' && $this->boolean('limit_per_user_uses')
                ? $this->input('max_uses_per_user')
                : null,
        ]);
    }
}
