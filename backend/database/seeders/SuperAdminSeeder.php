<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@aktivhub.app'],
            [
                'name'              => 'Super Admin',
                'password'          => env('SUPER_ADMIN_PASSWORD', 'HSt%9raX9K!UIz'),
                'role'              => UserRole::SuperAdmin->value,
                'email_verified_at' => now(),
            ]
        );
    }
}
