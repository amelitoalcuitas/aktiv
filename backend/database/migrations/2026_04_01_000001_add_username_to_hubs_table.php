<?php

use App\Models\Hub;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hubs', function (Blueprint $table): void {
            $table->string('username', 30)->nullable()->unique()->after('name');
            $table->timestamp('username_changed_at')->nullable()->after('username');
        });

        DB::table('hubs')->whereNull('username')->orderBy('id')->each(function (object $row): void {
            DB::table('hubs')
                ->where('id', $row->id)
                ->update([
                    'username' => Hub::generateUsername($row->name ?? 'hub'),
                ]);
        });
    }

    public function down(): void
    {
        Schema::table('hubs', function (Blueprint $table): void {
            $table->dropColumn(['username', 'username_changed_at']);
        });
    }
};
