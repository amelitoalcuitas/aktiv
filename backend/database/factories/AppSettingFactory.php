<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AppSetting>
 */
class AppSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'setting_'.Str::lower(Str::random(8)),
            'value' => (string) fake()->randomFloat(2, 10, 500),
            'description' => fake()->sentence(),
        ];
    }
}
