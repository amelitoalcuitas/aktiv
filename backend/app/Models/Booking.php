<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Booking extends Model
{
    use HasFactory;
    protected $fillable = [
        'booking_code',
        'court_id',
        'booked_by',
        'sport',
        'start_time',
        'end_time',
        'session_type',
        'status',
        'booking_source',
        'created_by',
        'guest_name',
        'guest_phone',
        'guest_email',
        'total_price',
        'receipt_image_url',
        'receipt_uploaded_at',
        'payment_note',
        'payment_confirmed_by',
        'payment_confirmed_at',
        'expires_at',
        'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'receipt_uploaded_at' => 'datetime',
            'payment_confirmed_at' => 'datetime',
            'expires_at' => 'datetime',
            'total_price' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Booking $booking): void {
            if (empty($booking->booking_code)) {
                do {
                    $code = Str::upper(Str::random(8));
                } while (static::where('booking_code', $code)->exists());

                $booking->booking_code = $code;
            }
        });
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paymentConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_confirmed_by');
    }
}
