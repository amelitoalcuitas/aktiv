<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Hub>
 */
class HubFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory()->state(['role' => UserRole::Admin]),
            'name' => fake()->company(),
            'description' => fake()->sentence(16),
            'city' => fake()->city(),
            'address' => fake()->address(),
            'lat' => fake()->latitude(),
            'lng' => fake()->longitude(),
            'cover_image_url' => fake()->imageUrl(1280, 720),
            'is_approved' => true,
            'is_verified' => false,
        ];
    }
}
