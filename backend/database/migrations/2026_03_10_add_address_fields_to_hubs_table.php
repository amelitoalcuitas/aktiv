<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hubs', function (Blueprint $table) {
            $table->string('address_line2', 500)->nullable()->after('landmark');
            $table->string('zip_code', 20)->nullable()->after('city');
            $table->string('province', 255)->nullable()->after('zip_code');
            $table->string('country', 255)->nullable()->after('province');
        });
    }

    public function down(): void
    {
        Schema::table('hubs', function (Blueprint $table) {
            $table->dropColumn(['address_line2', 'zip_code', 'province', 'country']);
        });
    }
};
