<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hub_contact_numbers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hub_id')->constrained('hubs')->cascadeOnDelete();
            $table->enum('type', ['mobile', 'landline']);
            $table->string('number', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_contact_numbers');
    }
};
