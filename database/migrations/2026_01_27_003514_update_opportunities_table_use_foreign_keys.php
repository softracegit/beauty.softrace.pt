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
        Schema::table('opportunities', function (Blueprint $table) {
            // Adicionar novos campos com foreign keys
            $table->foreignId('transaction_type_id')->nullable()->after('agent_id')->constrained('transaction_types')->nullOnDelete();
            $table->foreignId('property_typology_id')->nullable()->after('transaction_type_id')->constrained('property_typologies')->nullOnDelete();
            
            // Adicionar campos de localização com IDs
            $table->unsignedInteger('id_district')->nullable()->after('property_typology_id');
            $table->unsignedInteger('id_city')->nullable()->after('id_district');
            $table->unsignedInteger('id_parish')->nullable()->after('id_city');
        });

        // Remover colunas antigas
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropColumn(['transaction_type', 'preferred_typology', 'preferred_district', 'preferred_city', 'preferred_parish']);
        });

        // Adicionar novos índices
        Schema::table('opportunities', function (Blueprint $table) {
            $table->index('transaction_type_id');
            $table->index('property_typology_id');
            $table->index('id_district');
            $table->index('id_city');
            $table->index('id_parish');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            // Remover índices novos
            $table->dropIndex(['transaction_type_id']);
            $table->dropIndex(['property_typology_id']);
            $table->dropIndex(['id_district']);
            $table->dropIndex(['id_city']);
            $table->dropIndex(['id_parish']);
        });

        Schema::table('opportunities', function (Blueprint $table) {
            // Adicionar colunas antigas de volta
            $table->string('transaction_type')->nullable()->after('agent_id');
            $table->string('preferred_typology')->nullable()->after('max_price');
            $table->string('preferred_district')->nullable()->after('preferred_typology');
            $table->string('preferred_city')->nullable()->after('preferred_district');
            $table->string('preferred_parish')->nullable()->after('preferred_city');
        });

        Schema::table('opportunities', function (Blueprint $table) {
            // Remover foreign keys e colunas novas
            $table->dropForeign(['transaction_type_id']);
            $table->dropForeign(['property_typology_id']);
            $table->dropColumn(['transaction_type_id', 'property_typology_id', 'id_district', 'id_city', 'id_parish']);
        });
    }
};
