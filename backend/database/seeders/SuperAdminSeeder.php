<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SUPER_ADMIN_PASSWORD');

        if (! $password) {
            $this->command->error('SUPER_ADMIN_PASSWORD env variable is not set.');
            return;
        }

        User::query()->updateOrCreate(
            ['email' => 'admin@aktivhub.app'],
            [
                'first_name'        => 'Super',
                'last_name'         => 'Admin',
                'username'          => 'superadmin',
                'password'          => $password,
                'role'              => UserRole::SuperAdmin->value,
                'email_verified_at' => now(),
            ]
        );
    }
}
