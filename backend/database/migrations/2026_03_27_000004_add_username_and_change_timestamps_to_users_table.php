<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        // Use DB::table() throughout to avoid SoftDeletes scope referencing deleted_at
        // (deleted_at may not exist yet at this point in the migration sequence)
        DB::table('users')->whereNull('username')->orderBy('id')->each(function (object $row): void {
            $base = strtolower(preg_replace('/[^a-z0-9]/i', '', \Illuminate\Support\Str::ascii(($row->first_name ?? 'user') . ($row->last_name ?? ''))));

            if ($base === '') {
                $base = 'user';
            }

            $username = $base;
            $counter  = 1;

            while (DB::table('users')->where('username', $username)->exists()) {
                $username = $base . $counter++;
            }

            DB::table('users')->where('id', $row->id)->update(['username' => $username]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['username', 'username_changed_at', 'name_changed_at']);
        });
    }
};
