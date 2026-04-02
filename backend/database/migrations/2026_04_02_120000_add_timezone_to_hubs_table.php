<?php

use App\Support\HubTimezone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hubs', function (Blueprint $table): void {
            $table->string('timezone')->default(HubTimezone::DEFAULT_TIMEZONE)->after('lng');
        });

        DB::table('hubs')
            ->whereNull('timezone')
            ->update(['timezone' => HubTimezone::DEFAULT_TIMEZONE]);
    }

    public function down(): void
    {
        Schema::table('hubs', function (Blueprint $table): void {
            $table->dropColumn('timezone');
        });
    }
};
