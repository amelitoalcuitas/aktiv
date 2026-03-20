<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hubs', function (Blueprint $table): void {
            $table->string('digital_bank_name')->nullable()->after('payment_qr_url');
            $table->string('digital_bank_account')->nullable()->after('digital_bank_name');
        });
    }

    public function down(): void
    {
        Schema::table('hubs', function (Blueprint $table): void {
            $table->dropColumn(['digital_bank_name', 'digital_bank_account']);
        });
    }
};
