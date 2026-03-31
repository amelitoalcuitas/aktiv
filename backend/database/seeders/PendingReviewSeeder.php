<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Hub;
use App\Models\User;
use Illuminate\Database\Seeder;

class PendingReviewSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->where('email', 'user@user.com')->firstOrFail();
        $owner = User::query()->where('email', 'owner@owner.com')->first();

        // Create 5 hubs (with courts) owned by owner, each with one past confirmed booking for the user
        Hub::factory(5)
            ->create(['owner_id' => $owner?->id ?? $user->id])
            ->each(function (Hub $hub) use ($user): void {
                $court = Court::factory()->create(['hub_id' => $hub->id]);

                Booking::factory()->create([
                    'court_id'   => $court->id,
                    'booked_by'  => $user->id,
                    'created_by' => $user->id,
                    'status'     => 'confirmed',
                    'start_time' => now()->subHours(3),
                    'end_time'   => now()->subHours(2),
                    'expires_at' => now()->subHours(2),
                ]);
            });
    }
}
