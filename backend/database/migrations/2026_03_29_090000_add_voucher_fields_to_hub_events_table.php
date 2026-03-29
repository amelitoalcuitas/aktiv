<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hub_events', function (Blueprint $table): void {
            $table->string('voucher_code', 12)->nullable()->after('discount_value');
            $table->boolean('show_announcement')->default(true)->after('voucher_code');
            $table->string('title')->nullable()->change();
            $table->unique(['hub_id', 'voucher_code']);
        });
    }

    public function down(): void
    {
        Schema::table('hub_events', function (Blueprint $table): void {
            $table->dropUnique(['hub_id', 'voucher_code']);
            $table->dropColumn(['voucher_code', 'show_announcement']);
            $table->string('title')->nullable(false)->change();
        });
    }
};
