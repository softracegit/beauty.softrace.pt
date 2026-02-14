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
        Schema::create('property_preference_typologies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_preference_id')->constrained('client_property_preferences')->onDelete('cascade');
            $table->foreignId('property_typology_id')->constrained('property_typologies')->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['property_preference_id', 'property_typology_id'], 'pref_typology_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_preference_typologies');
    }
};
