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
        Schema::table('hub_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('guest_booking_limit')->default(1)->after('require_account_to_book');
            $table->unsignedTinyInteger('guest_max_hours')->default(2)->after('guest_booking_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hub_settings', function (Blueprint $table) {
            $table->dropColumn(['guest_booking_limit', 'guest_max_hours']);
        });
    }
};
