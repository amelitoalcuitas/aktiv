<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hubs', function (Blueprint $table): void {
            $table->unsignedInteger('discovery_boost_weight')->default(0)->after('show_on_profile');
            $table->timestamp('discovery_boost_expires_at')->nullable()->after('discovery_boost_weight');
        });
    }

    public function down(): void
    {
        Schema::table('hubs', function (Blueprint $table): void {
            $table->dropColumn(['discovery_boost_weight', 'discovery_boost_expires_at']);
        });
    }
};
