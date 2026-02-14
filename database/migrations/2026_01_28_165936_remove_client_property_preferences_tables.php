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
        // Remover tabelas pivot primeiro (têm foreign keys)
        Schema::dropIfExists('property_preference_features');
        Schema::dropIfExists('property_preference_typologies');
        Schema::dropIfExists('property_preference_locations');
        
        // Remover tabela de localizações de preferências
        Schema::dropIfExists('preference_locations');
        
        // Remover tabela principal de preferências de clientes
        Schema::dropIfExists('client_property_preferences');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recriar tabela principal
        Schema::create('client_property_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->foreignId('property_type_id')->constrained('property_types')->onDelete('cascade');
            $table->foreignId('transaction_type_id')->constrained('transaction_types')->onDelete('cascade');
            $table->foreignId('property_condition_id')->nullable()->constrained('property_conditions')->onDelete('set null');
            $table->unsignedInteger('id_district')->nullable();
            $table->unsignedInteger('id_city')->nullable();
            $table->unsignedInteger('id_parish')->nullable();
            $table->decimal('max_price', 15, 2)->nullable();
            $table->decimal('min_price', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        
        // Recriar tabela de localizações
        Schema::create('preference_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_preference_id')->constrained('client_property_preferences')->onDelete('cascade');
            $table->unsignedInteger('id_district')->nullable();
            $table->unsignedInteger('id_city')->nullable();
            $table->unsignedInteger('id_parish')->nullable();
            $table->timestamps();
        });
        
        // Recriar tabelas pivot
        Schema::create('property_preference_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_preference_id')->constrained('client_property_preferences')->onDelete('cascade');
            $table->foreignId('location_id')->constrained('locations')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['property_preference_id', 'location_id'], 'pref_location_unique');
        });
        
        Schema::create('property_preference_typologies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_preference_id')->constrained('client_property_preferences')->onDelete('cascade');
            $table->foreignId('property_typology_id')->constrained('property_typologies')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['property_preference_id', 'property_typology_id'], 'pref_typology_unique');
        });
        
        Schema::create('property_preference_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_preference_id')->constrained('client_property_preferences')->onDelete('cascade');
            $table->foreignId('property_feature_id')->constrained('property_features')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['property_preference_id', 'property_feature_id'], 'pref_feature_unique');
        });
    }
};
