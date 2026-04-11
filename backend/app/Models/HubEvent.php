<?php

namespace App\Models;

use App\Support\HubTimezone;
use Carbon\Carbon;
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
        'start_time',
        'end_time',
        'discount_type',
        'discount_value',
        'voucher_code',
        'show_announcement',
        'limit_total_uses',
        'max_total_uses',
        'limit_per_user_uses',
        'max_uses_per_user',
        'affected_courts',
        'court_discounts',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_time'      => 'datetime',
            'end_time'        => 'datetime',
            'affected_courts' => 'array',
            'court_discounts' => 'array',
            'is_active'       => 'boolean',
            'show_announcement' => 'boolean',
            'limit_total_uses' => 'boolean',
            'max_total_uses' => 'integer',
            'limit_per_user_uses' => 'boolean',
            'max_uses_per_user' => 'integer',
            'discount_value'  => 'decimal:2',
        ];
    }

    public function hub(): BelongsTo
    {
        return $this->belongsTo(Hub::class);
    }

    public function startsAtInTimezone(?string $timezone = null): Carbon
    {
        return $this->start_time->copy()->setTimezone(HubTimezone::resolve($timezone ?? $this->hub?->timezone));
    }

    public function endsAtInTimezone(?string $timezone = null): Carbon
    {
        return $this->end_time->copy()->setTimezone(HubTimezone::resolve($timezone ?? $this->hub?->timezone));
    }

    public function overlapsWindow(Carbon $startTime, Carbon $endTime): bool
    {
        return $this->start_time->lt($endTime) && $this->end_time->gt($startTime);
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
