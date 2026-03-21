<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('expired_booking_strikes')->default(0)->after('inapp_notifications_enabled');
            $table->timestamp('strikes_reset_at')->nullable()->after('expired_booking_strikes');
            $table->timestamp('booking_banned_until')->nullable()->after('strikes_reset_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['expired_booking_strikes', 'strikes_reset_at', 'booking_banned_until']);
        });
    }
};
