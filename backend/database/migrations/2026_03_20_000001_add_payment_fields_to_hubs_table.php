<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hubs', function (Blueprint $table) {
            $table->json('payment_methods')->default('["pay_on_site"]')->after('require_account_to_book');
            $table->string('payment_qr_url')->nullable()->after('payment_methods');
        });
    }

    public function down(): void
    {
        Schema::table('hubs', function (Blueprint $table) {
            $table->dropColumn(['payment_methods', 'payment_qr_url']);
        });
    }
};
