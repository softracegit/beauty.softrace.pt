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
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            
            // Relações
            $table->foreignId('opportunity_id')->constrained()->onDelete('cascade');
            $table->foreignId('proposal_id')->constrained()->onDelete('cascade');
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            
            // Snapshot do imóvel no momento do fecho
            $table->string('property_reference');
            $table->string('property_title');
            $table->string('property_address')->nullable();
            $table->string('transaction_type'); // Venda, Arrendamento
            
            // Valores do negócio
            $table->decimal('final_price', 15, 2); // Valor final da proposta aceite
            $table->decimal('property_commission_value', 15, 2)->nullable(); // Comissão do imóvel (valor absoluto)
            $table->decimal('property_commission_percentage', 5, 2)->nullable(); // Comissão do imóvel (percentagem)
            
            // Estado
            $table->string('status')->default('fechado'); // fechado, revertido
            $table->timestamp('closed_at');
            $table->foreignId('closed_by')->constrained('users')->onDelete('cascade');
            
            // Reversão (se aplicável)
            $table->timestamp('reverted_at')->nullable();
            $table->foreignId('reverted_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('reversion_reason')->nullable();
            
            // Notas
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->index('status');
            $table->index('closed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
