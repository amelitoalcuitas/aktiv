<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OwnerBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_code' => $this->booking_code,
            'hub_name' => $this->getAttribute('hub_name'),
            'court_id' => $this->court_id,
            'court' => $this->court ? [
                'id' => $this->court->id,
                'name' => $this->court->name,
                'hub_id' => $this->court->hub_id,
            ] : null,
            'booked_by' => $this->booked_by,
            'booked_by_user' => $this->bookedBy ? [
                'id' => $this->bookedBy->id,
                'first_name' => $this->bookedBy->first_name,
                'last_name' => $this->bookedBy->last_name,
                'email' => $this->bookedBy->email,
                'contact_number' => $this->bookedBy->contact_number,
                'avatar_url' => $this->bookedBy->avatar_url,
            ] : null,
            'guest_name' => $this->guest_name,
            'guest_email' => $this->guest_email,
            'guest_phone' => $this->guest_phone,
            'sport' => $this->sport,
            'start_time' => $this->start_time->toIso8601String(),
            'end_time' => $this->end_time->toIso8601String(),
            'session_type' => $this->session_type,
            'status' => $this->status,
            'booking_source' => $this->booking_source,
            'total_price' => $this->total_price,
            'receipt_image_url' => $this->receipt_image_url,
            'receipt_uploaded_at' => $this->receipt_uploaded_at?->toIso8601String(),
            'payment_method' => $this->payment_method,
            'payment_note' => $this->payment_note,
            'payment_confirmed_by' => $this->payment_confirmed_by,
            'payment_confirmed_at' => $this->payment_confirmed_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'cancelled_by' => $this->cancelled_by,
            'open_play_session_id' => $this->openPlaySession?->id,
            'open_play_session' => $this->openPlaySession ? [
                'id' => $this->openPlaySession->id,
                'booking_id' => $this->openPlaySession->booking_id,
                'max_players' => $this->openPlaySession->max_players,
                'price_per_player' => $this->openPlaySession->price_per_player,
                'notes' => $this->openPlaySession->notes,
                'guests_can_join' => $this->openPlaySession->guests_can_join,
                'status' => $this->openPlaySession->status,
                'participants_count' => $this->openPlaySession->participants_count ?? 0,
                'confirmed_participants_count' => $this->openPlaySession->confirmed_participants_count ?? 0,
                'viewer_participant' => null,
                'created_at' => $this->openPlaySession->created_at->toIso8601String(),
            ] : null,
            'open_play_participants' => $this->openPlaySession && $this->openPlaySession->relationLoaded('participants')
                ? OpenPlayParticipantResource::collection($this->openPlaySession->participants)->resolve()
                : [],
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
