<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hub_id')->constrained('hubs')->cascadeOnDelete();
            $table->string('name');
            $table->string('surface')->nullable();
            $table->boolean('indoor')->default(false);
            $table->decimal('price_per_hour', 10, 2)->default(0);
            $table->unsignedInteger('max_players')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['hub_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courts');
    }
};
