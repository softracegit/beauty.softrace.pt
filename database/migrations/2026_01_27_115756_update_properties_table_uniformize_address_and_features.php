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
            // Adicionar campos de morada seguindo modelo dos Clientes
            $table->string('door')->nullable()->after('address')->comment('Porta');
            $table->string('floor_address')->nullable()->after('door')->comment('Andar da morada');
            $table->string('side')->nullable()->after('floor_address')->comment('Lado');
            $table->string('locality')->nullable()->after('postal_code')->comment('Localidade');
            
            // Adicionar campo de condição
            $table->foreignId('property_condition_id')->nullable()->constrained('property_conditions')->nullOnDelete()->after('status');
            
            // Remover campo active (o status já indica disponibilidade)
            $table->dropColumn('active');
            
            // Remover campo bedrooms (tipologia já representa quartos)
            $table->dropColumn('bedrooms');
            
            // Remover campos booleanos que serão substituídos por property_features
            // Estes serão migrados para a tabela pivot property_property_feature
            $table->dropColumn(['elevator', 'furnished', 'balcony', 'terrace', 'storage', 'air_conditioning', 'heating']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Reverter campos de morada
            $table->dropColumn(['door', 'floor_address', 'side', 'locality']);
            
            // Reverter campo de condição
            $table->dropForeign(['property_condition_id']);
            $table->dropColumn('property_condition_id');
            
            // Restaurar campo active
            $table->boolean('active')->default(true)->after('status');
            
            // Restaurar campo bedrooms
            $table->integer('bedrooms')->nullable()->after('area_private');
            
            // Restaurar campos booleanos
            $table->boolean('elevator')->default(false)->after('floor');
            $table->boolean('furnished')->default(false)->after('elevator');
            $table->boolean('balcony')->default(false)->after('orientation');
            $table->boolean('terrace')->default(false)->after('balcony');
            $table->boolean('storage')->default(false)->after('terrace');
            $table->boolean('air_conditioning')->default(false)->after('storage');
            $table->boolean('heating')->default(false)->after('air_conditioning');
        });
    }
};
