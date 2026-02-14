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
        Schema::create('deal_agent_commissions', function (Blueprint $table) {
            $table->id();
            
            // Relações
            $table->foreignId('deal_id')->constrained()->onDelete('cascade');
            $table->foreignId('agent_id')->constrained()->onDelete('cascade');
            
            // Tipo de participação do agente
            $table->string('role'); // angariador, vendedor, etc.
            
            // Snapshot do agente no momento do fecho
            $table->string('agent_name');
            $table->string('agent_email')->nullable();
            
            // Comissão
            $table->decimal('commission_value', 15, 2)->nullable(); // Valor absoluto
            $table->decimal('commission_percentage', 5, 2)->nullable(); // Percentagem
            
            $table->timestamps();
            
            // Índices
            $table->index(['deal_id', 'agent_id']);
            $table->index('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deal_agent_commissions');
    }
};
