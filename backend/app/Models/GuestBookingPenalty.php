<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GuestBookingPenalty extends Model
{
    use HasUuids;

    protected $fillable = [
        'email',
        'strikes',
        'strikes_reset_at',
        'banned_until',
    ];

    protected function casts(): array
    {
        return [
            'strikes_reset_at' => 'datetime',
            'banned_until'     => 'datetime',
        ];
    }

    public function isBanned(): bool
    {
        return $this->banned_until !== null && $this->banned_until->isFuture();
    }
}
