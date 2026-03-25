<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HubEvent extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'hub_id',
        'title',
        'description',
        'event_type',
        'date_from',
        'date_to',
        'time_from',
        'time_to',
        'discount_type',
        'discount_value',
        'affected_courts',
        'court_discounts',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'date_from'       => 'date',
            'date_to'         => 'date',
            'affected_courts' => 'array',
            'court_discounts' => 'array',
            'is_active'       => 'boolean',
            'discount_value'  => 'decimal:2',
        ];
    }

    public function hub(): BelongsTo
    {
        return $this->belongsTo(Hub::class);
    }

    /**
     * Whether this event applies to the given court UUID.
     * For promos with court_discounts set, checks the per-court list.
     * For all other cases, falls back to affected_courts (null = all courts).
     */
    public function appliesToCourt(string $courtId): bool
    {
        if ($this->court_discounts !== null) {
            return collect($this->court_discounts)->contains('court_id', $courtId);
        }

        return $this->affected_courts === null || in_array($courtId, $this->affected_courts, true);
    }

    /**
     * Returns the discount type and value applicable to the given court.
     * Falls back to the event-level global discount when no per-court entry exists.
     */
    public function discountForCourt(string $courtId): array
    {
        if ($this->court_discounts !== null) {
            $entry = collect($this->court_discounts)->firstWhere('court_id', $courtId);
            if ($entry) {
                return [
                    'discount_type'  => $entry['discount_type'],
                    'discount_value' => (float) $entry['discount_value'],
                ];
            }
        }

        return [
            'discount_type'  => $this->discount_type,
            'discount_value' => (float) $this->discount_value,
        ];
    }
}
