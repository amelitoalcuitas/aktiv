<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hub_events', function (Blueprint $table): void {
            $table->boolean('limit_total_uses')->default(false)->after('show_announcement');
            $table->unsignedInteger('max_total_uses')->nullable()->after('limit_total_uses');
            $table->boolean('limit_per_user_uses')->default(false)->after('max_total_uses');
            $table->unsignedInteger('max_uses_per_user')->nullable()->after('limit_per_user_uses');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignUuid('applied_hub_event_id')
                ->nullable()
                ->after('applied_promo_title')
                ->constrained('hub_events')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('applied_hub_event_id');
        });

        Schema::table('hub_events', function (Blueprint $table): void {
            $table->dropColumn([
                'limit_total_uses',
                'max_total_uses',
                'limit_per_user_uses',
                'max_uses_per_user',
            ]);
        });
    }
};
