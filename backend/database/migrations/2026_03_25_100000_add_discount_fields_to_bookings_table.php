<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->decimal('original_price', 10, 2)->nullable()->after('total_price');
            $table->decimal('discount_amount', 10, 2)->nullable()->after('original_price');
            $table->string('applied_promo_title')->nullable()->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['original_price', 'discount_amount', 'applied_promo_title']);
        });
    }
};
