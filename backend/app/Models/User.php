<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar_url',
        'phone',
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
        ];
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
}
