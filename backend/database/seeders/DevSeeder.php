<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\Hub;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'user@user.com'],
            [
                'first_name'        => 'Test',
                'last_name'         => 'User',
                'username'          => 'testuser',
                'password'          => Hash::make('Test123!'),
                'email_verified_at' => now(),
            ]
        );

        $owner = User::query()->updateOrCreate(
            ['email' => 'owner@owner.com'],
            [
                'first_name'        => 'Test',
                'last_name'         => 'Owner',
                'username'          => 'testowner',
                'password'          => Hash::make('Test123!'),
                'email_verified_at' => now(),
            ]
        );

        $owner->update(['role' => \App\Enums\UserRole::Owner]);

        $hubs = Hub::factory(3)->create(['owner_id' => $owner->id]);

        $courtNames = ['Court A', 'Court B', 'Court C'];

        foreach ($hubs as $hub) {
            foreach ($courtNames as $name) {
                Court::factory()->create([
                    'hub_id' => $hub->id,
                    'name'   => $name,
                ]);
            }
        }
    }
}
