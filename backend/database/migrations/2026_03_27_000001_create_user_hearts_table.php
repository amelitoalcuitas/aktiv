<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_hearts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['from_user_id', 'to_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_hearts');
    }
};
