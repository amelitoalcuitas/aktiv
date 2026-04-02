<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardCalendarItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this['id'],
            'kind' => $this['kind'],
            'hub_id' => $this['hub_id'],
            'hub_name' => $this['hub_name'],
            'title' => $this['title'],
            'date' => $this['date'],
            'time_label' => $this['time_label'],
            'to' => $this['to'],
        ];
    }
}
