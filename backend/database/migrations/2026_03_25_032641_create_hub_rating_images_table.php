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
        Schema::create('hub_rating_images', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('hub_rating_id')->constrained('hub_ratings')->cascadeOnDelete();
            $table->string('storage_path');
            $table->string('url', 2048);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hub_rating_images');
    }
};
