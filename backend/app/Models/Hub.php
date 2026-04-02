<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Hub extends Model
{
    use HasFactory, HasUuids;

    public const USERNAME_REGEX = '/^(?![0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$)[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_id',
        'name',
        'username',
        'username_changed_at',
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
        'discovery_boost_weight',
        'discovery_boost_expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'username_changed_at' => 'datetime',
            'is_approved'      => 'boolean',
            'is_verified'      => 'boolean',
            'is_active'        => 'boolean',
            'show_on_profile'  => 'boolean',
            'discovery_boost_weight' => 'integer',
            'discovery_boost_expires_at' => 'datetime',
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

    public function members(): HasMany
    {
        return $this->hasMany(HubMember::class)->latest('created_at');
    }

    public function activeEvents(): HasMany
    {
        $today = now('Asia/Manila')->toDateString();

        return $this->hasMany(HubEvent::class)
            ->where('is_active', true)
            ->where('date_from', '<=', $today)
            ->where('date_to', '>=', $today);
    }

    public function resolveRouteBinding($value, $field = null): ?EloquentModel
    {
        $field ??= $this->getRouteKeyName();

        if ($field !== $this->getRouteKeyName()) {
            return $this->newQuery()->where($field, $value)->first();
        }

        return $this->newQuery()
            ->when(
                Str::isUuid($value),
                fn ($query) => $query->whereKey($value),
                fn ($query) => $query->where('username', $value)
            )
            ->first();
    }

    public static function normalizeUsername(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = Str::of($value)
            ->trim()
            ->lower()
            ->slug('-')
            ->value();

        return $value !== '' ? $value : null;
    }

    public static function generateUsername(string $name): string
    {
        $base = static::normalizeUsername($name) ?? 'hub';
        $username = Str::limit($base, 30, '');
        $counter = 1;

        while (static::query()->where('username', $username)->exists()) {
            $suffix = (string) $counter++;
            $username = Str::limit($base, 30 - strlen($suffix), '') . $suffix;
        }

        return $username;
    }
}
