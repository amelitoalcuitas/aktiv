<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Court extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'hub_id',
        'name',
        'surface',
        'indoor',
        'price_per_hour',
        'max_players',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'indoor' => 'boolean',
            'price_per_hour' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function hub(): BelongsTo
    {
        return $this->belongsTo(Hub::class);
    }

    public function sports(): HasMany
    {
        return $this->hasMany(CourtSport::class);
    }
}
