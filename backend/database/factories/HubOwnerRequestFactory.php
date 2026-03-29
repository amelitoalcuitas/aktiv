<?php

namespace Database\Factories;

use App\Enums\HubOwnerRequestStatus;
use App\Models\HubOwnerRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HubOwnerRequest>
 */
class HubOwnerRequestFactory extends Factory
{
    protected $model = HubOwnerRequest::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => HubOwnerRequestStatus::Pending,
            'hub_name' => fake()->company(),
            'city' => fake()->city(),
            'contact_number' => fake()->phoneNumber(),
            'message' => fake()->paragraph(),
            'review_notes' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }
}
