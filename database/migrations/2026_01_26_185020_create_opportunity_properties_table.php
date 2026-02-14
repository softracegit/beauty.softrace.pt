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
        Schema::create('opportunity_properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained('opportunities')->onDelete('cascade');
            $table->foreignId('property_id')->constrained('properties')->onDelete('cascade');
            $table->timestamp('attached_at')->useCurrent()->comment('Quando foi associado');
            $table->text('notes')->nullable()->comment('Notas específicas sobre esta associação');
            $table->timestamps();
            
            // Índices
            $table->unique(['opportunity_id', 'property_id'], 'opportunity_property_unique');
            $table->index('opportunity_id');
            $table->index('property_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opportunity_properties');
    }
};
