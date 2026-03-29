<?php

namespace App\Http\Requests\HubEvent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHubEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ownership enforced in controller
    }

    public function rules(): array
    {
        $eventType = $this->input('event_type', $this->route('event')?->event_type);
        $showAnnouncement = $this->has('show_announcement')
            ? $this->boolean('show_announcement')
            : (bool) $this->route('event')?->show_announcement;
        $isVoucher = $eventType === 'voucher';
        $limitTotalUses = $this->has('limit_total_uses')
            ? $this->boolean('limit_total_uses')
            : (bool) $this->route('event')?->limit_total_uses;
        $limitPerUserUses = $this->has('limit_per_user_uses')
            ? $this->boolean('limit_per_user_uses')
            : (bool) $this->route('event')?->limit_per_user_uses;
        $isPromoWithoutCourtDiscounts = $eventType === 'promo'
            && $this->hasAny(['discount_type', 'discount_value', 'court_discounts'])
            && empty($this->input('court_discounts'));

        return [
            'title'            => array_filter([
                'sometimes',
                'string',
                'max:100',
            ]),
            'description'      => array_filter([
                'nullable',
                'string',
                'max:500',
            ]),
            'event_type'       => ['sometimes', 'in:closure,promo,announcement,voucher'],
            'date_from'        => ['sometimes', 'date_format:Y-m-d'],
            'date_to'          => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'time_from'        => ['nullable', 'date_format:H:i'],
            'time_to'          => ['nullable', 'date_format:H:i', 'after:time_from'],
            'discount_type'              => array_filter(['nullable', $isPromoWithoutCourtDiscounts || $isVoucher ? 'required' : null, 'in:percent,flat']),
            'discount_value'             => array_filter(['nullable', $isPromoWithoutCourtDiscounts || $isVoucher ? 'required' : null, 'numeric', 'min:0']),
            'voucher_code'               => array_filter([
                'nullable',
                $isVoucher ? 'required' : null,
                'string',
                'size:12',
                'regex:/^[A-Z0-9]+$/',
                Rule::unique('hub_events', 'voucher_code')
                    ->where(fn ($query) => $query->where('hub_id', $this->route('hub')->id))
                    ->ignore($this->route('event')?->id),
            ]),
            'show_announcement'          => ['sometimes', 'boolean'],
            'limit_total_uses'          => ['sometimes', 'boolean'],
            'max_total_uses'            => array_filter([
                'nullable',
                $isVoucher && $limitTotalUses ? 'required' : null,
                $isVoucher ? 'integer' : null,
                $isVoucher ? 'min:1' : null,
            ]),
            'limit_per_user_uses'       => ['sometimes', 'boolean'],
            'max_uses_per_user'         => array_filter([
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
        $eventType = $this->input('event_type', $this->route('event')?->event_type);
        $showAnnouncement = $this->has('show_announcement')
            ? $this->boolean('show_announcement')
            : (bool) $this->route('event')?->show_announcement;
        $voucherCode = $this->input('voucher_code');

        $this->merge([
            'voucher_code' => is_string($voucherCode) ? strtoupper(trim($voucherCode)) : $voucherCode,
            'show_announcement' => $eventType === 'voucher' ? $showAnnouncement : true,
            'limit_total_uses' => $eventType === 'voucher'
                ? ($this->has('limit_total_uses') ? $this->boolean('limit_total_uses') : (bool) $this->route('event')?->limit_total_uses)
                : false,
            'max_total_uses' => $eventType === 'voucher'
                ? (($this->has('limit_total_uses') ? $this->boolean('limit_total_uses') : (bool) $this->route('event')?->limit_total_uses)
                    ? $this->input('max_total_uses', $this->route('event')?->max_total_uses)
                    : null)
                : null,
            'limit_per_user_uses' => $eventType === 'voucher'
                ? ($this->has('limit_per_user_uses') ? $this->boolean('limit_per_user_uses') : (bool) $this->route('event')?->limit_per_user_uses)
                : false,
            'max_uses_per_user' => $eventType === 'voucher'
                ? (($this->has('limit_per_user_uses') ? $this->boolean('limit_per_user_uses') : (bool) $this->route('event')?->limit_per_user_uses)
                    ? $this->input('max_uses_per_user', $this->route('event')?->max_uses_per_user)
                    : null)
                : null,
        ]);
    }
}
