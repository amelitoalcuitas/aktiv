<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('open_play_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('booking_id')->unique()->constrained('bookings')->cascadeOnDelete();
            $table->string('title')->default('Open Play');
            $table->string('sport')->nullable();
            $table->integer('max_players');
            $table->decimal('price_per_player', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('guests_can_join')->default(false);
            $table->enum('status', ['open', 'full', 'cancelled', 'completed'])->default('open');
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('open_play_sessions');
    }
};
