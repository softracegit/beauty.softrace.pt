<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_time_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('icon')->default('ph-dots-three'); // Phosphor icon class
            $table->unsignedInteger('duration')->default(60); // minutos
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_time_types');
    }
};
