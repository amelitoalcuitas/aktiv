<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpenPlaySession extends Model
{
    use HasUuids;

    protected $fillable = [
        'booking_id',
        'sport',
        'max_players',
        'price_per_player',
        'notes',
        'guests_can_join',
        'status',
        'start_notification_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'price_per_player'            => 'decimal:2',
            'guests_can_join'             => 'boolean',
            'max_players'                 => 'integer',
            'start_notification_sent_at'  => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(OpenPlayParticipant::class);
    }

    public function confirmedParticipantCount(): int
    {
        return $this->participants()->where('payment_status', 'confirmed')->count();
    }

    public function recalculateStatus(): void
    {
        if (in_array($this->status, ['cancelled', 'completed'])) {
            return;
        }

        $confirmed = $this->confirmedParticipantCount();
        $newStatus = $confirmed >= $this->max_players ? 'full' : 'open';

        if ($this->status !== $newStatus) {
            $this->update(['status' => $newStatus]);
        }
    }
}
