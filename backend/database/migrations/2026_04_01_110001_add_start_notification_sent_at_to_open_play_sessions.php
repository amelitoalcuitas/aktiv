<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('open_play_sessions', function (Blueprint $table): void {
            $table->timestamp('start_notification_sent_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('open_play_sessions', function (Blueprint $table): void {
            $table->dropColumn('start_notification_sent_at');
        });
    }
};
