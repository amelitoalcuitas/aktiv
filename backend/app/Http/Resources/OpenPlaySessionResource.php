<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpenPlaySessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $booking = $this->whenLoaded('booking');
        $viewerParticipant = $this->viewer_participant;

        return [
            'id'               => $this->id,
            'booking_id'       => $this->booking_id,
            'title'            => $this->title,
            'description'      => $this->notes,
            'max_players'      => $this->max_players,
            'price_per_player' => $this->price_per_player,
            'notes'            => $this->notes,
            'guests_can_join'  => $this->guests_can_join,
            'status'           => $this->status,
            'booking'          => $booking ? [
                'id'         => $booking->id,
                'court_id'   => $booking->court_id,
                'court'      => $booking->relationLoaded('court') ? [
                    'id'   => $booking->court->id,
                    'name' => $booking->court->name,
                ] : null,
                'start_time' => $booking->start_time->toIso8601String(),
                'end_time'   => $booking->end_time->toIso8601String(),
                'status'     => $booking->status,
            ] : null,
            'participants_count'           => $this->participants_count ?? 0,
            'confirmed_participants_count' => $this->confirmed_participants_count ?? 0,
            'viewer_participant'           => $viewerParticipant
                ? OpenPlayParticipantResource::make($viewerParticipant)
                : null,
            'created_at'       => $this->created_at->toIso8601String(),
        ];
    }
}
