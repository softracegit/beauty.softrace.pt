<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('service_fee')) {
            return;
        }

        Schema::create('service_fee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('fee_id')->constrained('fees')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['service_id', 'fee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_fee');
    }
};
