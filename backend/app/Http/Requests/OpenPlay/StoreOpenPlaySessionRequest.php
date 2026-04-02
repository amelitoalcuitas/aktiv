<?php

namespace App\Http\Requests\OpenPlay;

use Illuminate\Foundation\Http\FormRequest;

class StoreOpenPlaySessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'            => ['required', 'string', 'max:120'],
            'court_id'         => ['required', 'uuid', 'exists:courts,id'],
            'start_time'       => ['required', 'date'],
            'end_time'         => ['required', 'date', 'after:start_time'],
            'max_players'      => ['required', 'integer', 'min:2'],
            'price_per_player' => ['required', 'numeric', 'min:0'],
            'description'      => ['nullable', 'string', 'max:500'],
            'guests_can_join'  => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_time.after'      => 'End time must be after start time.',
            'max_players.min'     => 'A session must allow at least 2 players.',
            'price_per_player.min' => 'Price per player cannot be negative.',
        ];
    }
}
