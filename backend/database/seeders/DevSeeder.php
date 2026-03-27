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

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'first_name'        => 'Test',
                'last_name'         => 'Admin',
                'username'          => 'testadmin',
                'password'          => Hash::make('Test123!'),
                'email_verified_at' => now(),
            ]
        );

        $admin->update(['role' => \App\Enums\UserRole::Admin]);

        $hubs = Hub::factory(3)->create(['owner_id' => $admin->id]);

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
