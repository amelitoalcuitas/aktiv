<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HubRating>
 */
class HubRatingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hub_id'     => \App\Models\Hub::factory(),
            'user_id'    => \App\Models\User::factory(),
            'booking_id' => null,
            'rating'     => $this->faker->numberBetween(1, 5),
            'comment'    => $this->faker->optional()->sentence(),
        ];
    }
}
