<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hub_operating_hours', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('hub_id')->constrained('hubs')->cascadeOnDelete();
            $table->tinyInteger('day_of_week'); // 0 = Sunday … 6 = Saturday
            $table->time('opens_at');
            $table->time('closes_at');
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->unique(['hub_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_operating_hours');
    }
};
