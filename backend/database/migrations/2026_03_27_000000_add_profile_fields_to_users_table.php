<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('bio')->nullable()->after('phone');
            $table->string('banner_url')->nullable()->after('avatar_url');
            $table->jsonb('social_links')->nullable()->after('bio');
            $table->jsonb('profile_privacy')->nullable()->after('social_links');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['bio', 'banner_url', 'social_links', 'profile_privacy']);
        });
    }
};
