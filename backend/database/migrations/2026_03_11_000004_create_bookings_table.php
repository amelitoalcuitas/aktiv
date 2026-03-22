<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('court_id')->constrained('courts')->cascadeOnDelete();
            $table->foreignUuid('booked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sport');
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->enum('session_type', ['private', 'open_play'])->default('private');
            $table->enum('status', ['pending_payment', 'payment_sent', 'confirmed', 'cancelled', 'completed'])->default('pending_payment');
            $table->enum('booking_source', ['self_booked', 'owner_added'])->default('self_booked');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('guest_phone')->nullable();
            $table->decimal('total_price', 10, 2)->nullable();
            $table->string('receipt_image_url')->nullable();
            $table->timestamp('receipt_uploaded_at')->nullable();
            $table->text('payment_note')->nullable();
            $table->foreignUuid('payment_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('payment_confirmed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->enum('cancelled_by', ['user', 'owner', 'system'])->nullable();
            $table->timestamps();

            // Index used in conflict detection and calendar queries
            $table->index(['court_id', 'status', 'start_time', 'end_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
