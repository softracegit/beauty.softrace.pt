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
        Schema::create('opportunity_preference_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_preference_id')->constrained('opportunity_property_preferences')->onDelete('cascade');
            $table->unsignedInteger('id_district')->nullable();
            $table->unsignedInteger('id_city')->nullable();
            $table->unsignedInteger('id_parish')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opportunity_preference_locations');
    }
};
