<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('open_play_sessions', 'title')) {
            return;
        }

        Schema::table('open_play_sessions', function (Blueprint $table): void {
            $table->string('title')->default('Open Play')->after('booking_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('open_play_sessions', 'title')) {
            return;
        }

        Schema::table('open_play_sessions', function (Blueprint $table): void {
            $table->dropColumn('title');
        });
    }
};
