<?php

namespace Database\Factories;

use App\Models\Court;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Booking>
 */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 day', '+30 days');
        $end = (clone $start)->modify('+1 hour');

        return [
            'court_id'       => Court::factory(),
            'booked_by'      => null,
            'created_by'     => null,
            'sport'          => 'badminton',
            'start_time'     => $start,
            'end_time'       => $end,
            'session_type'   => 'private',
            'status'         => 'pending_payment',
            'booking_source' => 'self_booked',
            'total_price'    => 200.00,
            'expires_at'     => now()->addHour(),
        ];
    }
}
