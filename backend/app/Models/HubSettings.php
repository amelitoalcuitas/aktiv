<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HubSettings extends Model
{
    use HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'hub_id',
        'require_account_to_book',
        'guest_booking_limit',
        'guest_max_hours',
        'payment_methods',
        'payment_qr_url',
        'digital_bank_name',
        'digital_bank_account',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'require_account_to_book' => 'boolean',
            'guest_booking_limit'     => 'integer',
            'guest_max_hours'         => 'integer',
            'payment_methods'         => 'array',
        ];
    }

    public function hub(): BelongsTo
    {
        return $this->belongsTo(Hub::class);
    }
}
