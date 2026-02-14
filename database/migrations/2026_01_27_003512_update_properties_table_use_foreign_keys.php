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
        Schema::table('properties', function (Blueprint $table) {
            // Adicionar novos campos com foreign keys
            $table->foreignId('transaction_type_id')->nullable()->after('active')->constrained('transaction_types')->nullOnDelete();
            $table->foreignId('property_type_id')->nullable()->after('transaction_type_id')->constrained('property_types')->nullOnDelete();
            $table->foreignId('property_typology_id')->nullable()->after('property_type_id')->constrained('property_typologies')->nullOnDelete();
            
            // Adicionar campos de localização com IDs
            $table->unsignedInteger('id_district')->nullable()->after('country');
            $table->unsignedInteger('id_city')->nullable()->after('id_district');
            $table->unsignedInteger('id_parish')->nullable()->after('id_city');
        });

        // Remover índices antigos antes de remover colunas
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['transaction_type']);
        });

        // Remover colunas antigas
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['transaction_type', 'typology', 'district', 'city', 'parish']);
        });

        // Adicionar novos índices
        Schema::table('properties', function (Blueprint $table) {
            $table->index('transaction_type_id');
            $table->index('property_type_id');
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
        Schema::table('properties', function (Blueprint $table) {
            // Remover índices novos
            $table->dropIndex(['transaction_type_id']);
            $table->dropIndex(['property_type_id']);
            $table->dropIndex(['property_typology_id']);
            $table->dropIndex(['id_district']);
            $table->dropIndex(['id_city']);
            $table->dropIndex(['id_parish']);
        });

        Schema::table('properties', function (Blueprint $table) {
            // Adicionar colunas antigas de volta
            $table->string('transaction_type')->nullable()->after('active');
            $table->string('typology')->nullable()->after('year_built');
            $table->string('district')->nullable()->after('country');
            $table->string('city')->nullable()->after('district');
            $table->string('parish')->nullable()->after('city');
        });

        Schema::table('properties', function (Blueprint $table) {
            // Remover foreign keys e colunas novas
            $table->dropForeign(['transaction_type_id']);
            $table->dropForeign(['property_type_id']);
            $table->dropForeign(['property_typology_id']);
            $table->dropColumn(['transaction_type_id', 'property_type_id', 'property_typology_id', 'id_district', 'id_city', 'id_parish']);
            
            // Adicionar índice antigo de volta
            $table->index('transaction_type');
        });
    }
};
