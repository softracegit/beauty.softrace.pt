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
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            
            // Identificação
            $table->string('reference')->unique()->comment('Código interno único');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('por_tratar')->comment('por_tratar, em_analise, imoveis_sugeridos, visitas_agendadas, proposta_negociacao, ganha, perdida, cancelada');
            
            // Relações
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            
            // Critérios de busca
            $table->string('transaction_type')->comment('venda, arrendamento');
            $table->decimal('min_price', 15, 2)->nullable();
            $table->decimal('max_price', 15, 2)->nullable();
            $table->string('preferred_typology')->nullable()->comment('T0, T1, T2, T3, T4, T5, etc');
            $table->string('preferred_district')->nullable();
            $table->string('preferred_city')->nullable();
            $table->string('preferred_parish')->nullable();
            
            // Outros
            $table->text('notes')->nullable();
            $table->timestamp('status_changed_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('status');
            $table->index('client_id');
            $table->index('lead_id');
            $table->index('agent_id');
            $table->index('reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
