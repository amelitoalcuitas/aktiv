<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hub_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hub_id')->constrained()->cascadeOnDelete();
            $table->boolean('require_account_to_book')->default(true);
            $table->json('payment_methods')->default('["pay_on_site"]');
            $table->string('payment_qr_url')->nullable();
            $table->string('digital_bank_name')->nullable();
            $table->string('digital_bank_account')->nullable();
            $table->timestamps();
        });

        // Migrate existing data from hubs to hub_settings
        DB::statement('
            INSERT INTO hub_settings (hub_id, require_account_to_book, payment_methods, payment_qr_url, digital_bank_name, digital_bank_account, created_at, updated_at)
            SELECT id, require_account_to_book, payment_methods, payment_qr_url, digital_bank_name, digital_bank_account, NOW(), NOW()
            FROM hubs
        ');

        Schema::table('hubs', function (Blueprint $table) {
            $table->dropColumn([
                'require_account_to_book',
                'payment_methods',
                'payment_qr_url',
                'digital_bank_name',
                'digital_bank_account',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('hubs', function (Blueprint $table) {
            $table->boolean('require_account_to_book')->default(true);
            $table->json('payment_methods')->default('["pay_on_site"]');
            $table->string('payment_qr_url')->nullable();
            $table->string('digital_bank_name')->nullable();
            $table->string('digital_bank_account')->nullable();
        });

        // Restore data from hub_settings back to hubs
        DB::statement('
            UPDATE hubs h
            SET require_account_to_book = hs.require_account_to_book,
                payment_methods = hs.payment_methods,
                payment_qr_url = hs.payment_qr_url,
                digital_bank_name = hs.digital_bank_name,
                digital_bank_account = hs.digital_bank_account
            FROM hub_settings hs
            WHERE hs.hub_id = h.id
        ');

        Schema::dropIfExists('hub_settings');
    }
};
