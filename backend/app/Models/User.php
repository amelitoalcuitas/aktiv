<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'username',
        'username_changed_at',
        'name_changed_at',
        'email',
        'password',
        'avatar_url',
        'avatar_thumb_url',
        'banner_url',
        'contact_number',
        'bio',
        'social_links',
        'profile_privacy',
        'google_id',
        'role',
        'email_notifications_enabled',
        'inapp_notifications_enabled',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'           => 'datetime',
            'password'                    => 'hashed',
            'role'                        => UserRole::class,
            'email_notifications_enabled' => 'boolean',
            'inapp_notifications_enabled' => 'boolean',
            'strikes_reset_at'            => 'datetime',
            'booking_banned_until'        => 'datetime',
            'username_changed_at'         => 'datetime',
            'name_changed_at'             => 'datetime',
            'social_links'                => 'array',
            'profile_privacy'             => 'array',
        ];
    }

    /**
     * Computed full name for convenience.
     */
    public function getNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    /**
     * Generate a unique username from first and last name.
     * Slugifies the combined name and appends an incrementing number on collision.
     */
    public static function generateUsername(string $firstName, string $lastName): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9]/i', '', Str::ascii($firstName . $lastName)));

        if ($base === '') {
            $base = 'user';
        }

        $username = $base;
        $counter  = 1;

        while (static::query()->where('username', $username)->exists()) {
            $username = $base . $counter++;
        }

        return $username;
    }

    public function isBookingBanned(): bool
    {
        return $this->booking_banned_until !== null && $this->booking_banned_until->isFuture();
    }

    public function bookingBanExpiresAt(): ?Carbon
    {
        return $this->isBookingBanned() ? $this->booking_banned_until : null;
    }

    public function isAdmin(): bool
    {
        return $this->role->isAdmin();
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function sendPasswordResetNotification(mixed $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function hubs(): HasMany
    {
        return $this->hasMany(Hub::class, 'owner_id');
    }

    public function heartsReceived(): HasMany
    {
        return $this->hasMany(UserHeart::class, 'to_user_id');
    }

    public function heartsSent(): HasMany
    {
        return $this->hasMany(UserHeart::class, 'from_user_id');
    }

    public function defaultPrivacy(): array
    {
        return [
            'show_visited_hubs'    => true,
            'show_leaderboard'     => true,
            'show_hearts'          => true,
            'show_tournaments'     => true,
            'show_open_play'       => true,
            'show_favorite_sports' => true,
        ];
    }

    public function resolvedPrivacy(): array
    {
        return array_merge($this->defaultPrivacy(), $this->profile_privacy ?? []);
    }
}
