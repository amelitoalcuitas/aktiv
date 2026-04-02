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
            $table->string('sport')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('open_play_sessions', function (Blueprint $table): void {
            $table->string('sport')->nullable(false)->change();
        });
    }
};
