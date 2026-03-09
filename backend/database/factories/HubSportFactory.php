<?php

namespace Database\Factories;

use App\Models\Hub;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HubSport>
 */
class HubSportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hub_id' => Hub::factory(),
            'sport' => fake()->randomElement(['tennis', 'badminton', 'basketball', 'pickleball']),
        ];
    }
}
