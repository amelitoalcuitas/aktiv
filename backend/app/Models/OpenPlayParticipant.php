<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpenPlayParticipant extends Model
{
    use HasUuids;

    protected $fillable = [
        'open_play_session_id',
        'user_id',
        'guest_name',
        'guest_phone',
        'guest_email',
        'guest_tracking_token',
        'payment_method',
        'payment_status',
        'receipt_image_url',
        'receipt_uploaded_at',
        'payment_note',
        'payment_confirmed_by',
        'payment_confirmed_at',
        'expires_at',
        'cancelled_by',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'receipt_uploaded_at'  => 'datetime',
            'payment_confirmed_at' => 'datetime',
            'expires_at'           => 'datetime',
            'joined_at'            => 'datetime',
        ];
    }

    public function openPlaySession(): BelongsTo
    {
        return $this->belongsTo(OpenPlaySession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function paymentConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_confirmed_by');
    }
}
