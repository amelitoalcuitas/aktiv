<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 30)->nullable()->unique()->after('last_name');
            $table->timestamp('username_changed_at')->nullable()->after('username');
            $table->timestamp('name_changed_at')->nullable()->after('username_changed_at');
        });

        // Backfill usernames for existing users
        User::query()->whereNull('username')->each(function (User $user): void {
            $user->update(['username' => User::generateUsername($user->first_name ?? 'user', $user->last_name ?? '')]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['username', 'username_changed_at', 'name_changed_at']);
        });
    }
};
