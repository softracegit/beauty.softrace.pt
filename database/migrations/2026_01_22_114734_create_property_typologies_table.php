<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('property_typologies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // T1, T2, T3, T4, T5+
            $table->string('slug')->unique();
            $table->integer('bedrooms')->nullable(); // Número de quartos
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_typologies');
    }
};
