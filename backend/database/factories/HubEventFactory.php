<?php

namespace Database\Factories;

use App\Models\Hub;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HubEvent>
 */
class HubEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hub_id'          => Hub::factory(),
            'title'           => fake()->sentence(3),
            'description'     => fake()->sentence(10),
            'event_type'      => 'announcement',
            'date_from'       => now('Asia/Manila')->toDateString(),
            'date_to'         => now('Asia/Manila')->addDays(7)->toDateString(),
            'time_from'       => null,
            'time_to'         => null,
            'discount_type'   => null,
            'discount_value'  => null,
            'affected_courts' => null,
            'is_active'       => true,
        ];
    }

    public function closure(): static
    {
        return $this->state(['event_type' => 'closure', 'discount_type' => null, 'discount_value' => null]);
    }

    public function promo(string $discountType = 'percent', float $discountValue = 20): static
    {
        return $this->state([
            'event_type'     => 'promo',
            'discount_type'  => $discountType,
            'discount_value' => $discountValue,
        ]);
    }
}
