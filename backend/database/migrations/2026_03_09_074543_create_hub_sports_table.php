<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hub_sports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hub_id')->constrained('hubs')->cascadeOnDelete();
            $table->string('sport');
            $table->timestamps();

            $table->unique(['hub_id', 'sport']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_sports');
    }
};
