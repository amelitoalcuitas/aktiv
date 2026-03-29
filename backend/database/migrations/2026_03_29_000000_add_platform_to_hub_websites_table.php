<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hub_websites', function (Blueprint $table): void {
            $table->string('platform', 32)->default('other')->after('hub_id');
        });
    }

    public function down(): void
    {
        Schema::table('hub_websites', function (Blueprint $table): void {
            $table->dropColumn('platform');
        });
    }
};
