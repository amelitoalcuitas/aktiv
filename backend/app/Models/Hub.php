<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Hub extends Model
{
    use HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_id',
        'name',
        'description',
        'city',
        'zip_code',
        'province',
        'country',
        'address',
        'address_line2',
        'landmark',
        'lat',
        'lng',
        'cover_image_url',
        'cover_image_path',
        'is_approved',
        'is_verified',
        'is_active',
        'show_on_profile',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'is_approved'      => 'boolean',
            'is_verified'      => 'boolean',
            'is_active'        => 'boolean',
            'show_on_profile'  => 'boolean',
        ];
    }

    public function settings(): HasOne
    {
        return $this->hasOne(HubSettings::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function courts(): HasMany
    {
        return $this->hasMany(Court::class);
    }

    public function sports(): HasMany
    {
        return $this->hasMany(HubSport::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(HubImage::class)->orderBy('order');
    }

    public function contactNumbers(): HasMany
    {
        return $this->hasMany(HubContactNumber::class)->orderBy('id');
    }

    public function websites(): HasMany
    {
        return $this->hasMany(HubWebsite::class)->orderBy('id');
    }

    public function operatingHours(): HasMany
    {
        return $this->hasMany(HubOperatingHours::class)->orderBy('day_of_week');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(HubRating::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(HubEvent::class);
    }

    public function activeEvents(): HasMany
    {
        $today = now('Asia/Manila')->toDateString();

        return $this->hasMany(HubEvent::class)
            ->where('is_active', true)
            ->where('date_from', '<=', $today)
            ->where('date_to', '>=', $today);
    }
}
