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
            $table->string('first_name', 100)->nullable()->after('id');
            $table->string('last_name', 100)->nullable()->after('first_name');
        });

        // Migrate existing name data (only runs if there are rows to migrate)
        if (DB::table('users')->count() > 0) {
            $driver = DB::connection()->getDriverName();

            if ($driver === 'pgsql') {
                DB::statement("
                    UPDATE users
                    SET first_name = SPLIT_PART(name, ' ', 1),
                        last_name  = NULLIF(TRIM(SUBSTRING(name FROM POSITION(' ' IN name))), '')
                ");
            } else {
                // SQLite fallback
                DB::statement("
                    UPDATE users
                    SET first_name = CASE
                            WHEN INSTR(name, ' ') > 0 THEN SUBSTR(name, 1, INSTR(name, ' ') - 1)
                            ELSE name
                        END,
                        last_name = CASE
                            WHEN INSTR(name, ' ') > 0 THEN TRIM(SUBSTR(name, INSTR(name, ' ') + 1))
                            ELSE NULL
                        END
                ");
            }
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('name');
            $table->renameColumn('phone', 'contact_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('name')->nullable()->after('id');
            $table->renameColumn('contact_number', 'phone');
        });

        if (DB::table('users')->count() > 0) {
            DB::statement("
                UPDATE users
                SET name = TRIM(COALESCE(first_name, '') || ' ' || COALESCE(last_name, ''))
            ");
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
