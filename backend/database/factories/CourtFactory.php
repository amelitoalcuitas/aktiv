<?php

namespace Database\Factories;

use App\Models\Hub;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Court>
 */
class CourtFactory extends Factory
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
            'name' => 'Court '.fake()->randomElement(['A', 'B', 'C', 'D']),
            'surface' => fake()->randomElement(['hardcourt', 'clay', 'synthetic']),
            'indoor' => fake()->boolean(),
            'price_per_hour' => fake()->randomFloat(2, 150, 1200),
            'max_players' => fake()->randomElement([2, 4, 6, 10]),
            'is_active' => true,
        ];
    }
}
