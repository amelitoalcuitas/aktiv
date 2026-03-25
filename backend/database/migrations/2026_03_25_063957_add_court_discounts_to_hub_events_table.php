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
        Schema::table('hub_events', function (Blueprint $table) {
            $table->json('court_discounts')->nullable()->after('affected_courts');
        });
    }

    public function down(): void
    {
        Schema::table('hub_events', function (Blueprint $table) {
            $table->dropColumn('court_discounts');
        });
    }
};
