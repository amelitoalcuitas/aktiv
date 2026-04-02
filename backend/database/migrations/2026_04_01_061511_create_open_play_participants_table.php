<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('open_play_participants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('open_play_session_id')->constrained('open_play_sessions')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('guest_phone')->nullable();
            $table->string('guest_email')->nullable();
            $table->string('guest_tracking_token')->nullable()->unique();
            $table->enum('payment_method', ['pay_on_site', 'digital_bank']);
            $table->enum('payment_status', ['pending_payment', 'payment_sent', 'confirmed', 'cancelled'])->default('pending_payment');
            $table->string('receipt_image_url')->nullable();
            $table->timestamp('receipt_uploaded_at')->nullable();
            $table->text('payment_note')->nullable();
            $table->foreignUuid('payment_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('payment_confirmed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->enum('cancelled_by', ['user', 'owner', 'system'])->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            // For duplicate join check
            $table->index(['open_play_session_id', 'user_id']);
            // For expiry queries
            $table->index(['payment_status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('open_play_participants');
    }
};
