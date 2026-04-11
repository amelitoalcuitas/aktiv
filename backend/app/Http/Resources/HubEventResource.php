<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HubEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'hub_id'           => $this->hub_id,
            'title'            => $this->title,
            'description'      => $this->description,
            'event_type'       => $this->event_type,
            'start_time'       => $this->start_time?->toIso8601String(),
            'end_time'         => $this->end_time?->toIso8601String(),
            'discount_type'    => $this->discount_type,
            'discount_value'   => $this->discount_value,
            'voucher_code'     => $this->voucher_code,
            'show_announcement' => $this->show_announcement,
            'limit_total_uses' => $this->limit_total_uses,
            'max_total_uses' => $this->max_total_uses,
            'limit_per_user_uses' => $this->limit_per_user_uses,
            'max_uses_per_user' => $this->max_uses_per_user,
            'affected_courts'  => $this->affected_courts,
            'court_discounts'  => $this->court_discounts,
            'is_active'        => $this->is_active,
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
