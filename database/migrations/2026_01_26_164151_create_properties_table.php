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
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            
            // Identificação
            $table->string('reference')->unique()->comment('Código interno único');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('disponivel')->comment('disponivel, reservado, em_negociacao, vendido, arrendado, inativo, por_validar, em_validacao');
            $table->boolean('active')->default(true);
            
            // Negócio
            $table->string('transaction_type')->comment('venda, arrendamento');
            $table->decimal('price', 15, 2)->nullable();
            $table->decimal('condominium_fee', 10, 2)->nullable()->comment('Condomínio');
            $table->decimal('imi_value', 10, 2)->nullable()->comment('IMI');
            $table->decimal('commission_percentage', 5, 2)->nullable();
            $table->decimal('commission_value', 10, 2)->nullable();
            
            // Localização
            $table->string('country')->default('Portugal');
            $table->string('district')->nullable();
            $table->string('city')->nullable();
            $table->string('parish')->nullable();
            $table->string('address')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            
            // Características
            $table->string('typology')->nullable()->comment('T0, T1, T2, T3, T4, T5, etc');
            $table->decimal('area_total', 10, 2)->nullable()->comment('Área total em m²');
            $table->decimal('area_private', 10, 2)->nullable()->comment('Área privada em m²');
            $table->integer('bedrooms')->nullable();
            $table->integer('bathrooms')->nullable();
            $table->integer('garages')->nullable();
            $table->integer('parking_spaces')->nullable();
            $table->integer('floor')->nullable();
            $table->boolean('elevator')->default(false);
            $table->boolean('furnished')->default(false);
            $table->integer('year_built')->nullable();
            $table->string('energy_certificate')->nullable()->comment('A, B, C, D, E, F, G');
            
            // Detalhes
            $table->string('orientation')->nullable()->comment('N, S, E, W, NE, NW, SE, SW');
            $table->boolean('balcony')->default(false);
            $table->boolean('terrace')->default(false);
            $table->boolean('storage')->default(false);
            $table->boolean('air_conditioning')->default(false);
            $table->boolean('heating')->default(false);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('status');
            $table->index('active');
            $table->index('transaction_type');
            $table->index('reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
