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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // compra, arrendamento, angariacao
            $table->string('name');
            $table->string('origin'); // portal, site, presencial, telefone, email, etc
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('priority')->default('medium'); // low, medium, high, urgent
            $table->string('property_reference')->nullable(); // Referência do imóvel associado
            $table->string('status')->default('por_tratar'); // por_tratar, em_contacto, agendado, pendente, ganho, perdido
            $table->foreignId('agent_id')->nullable()->constrained('agents')->nullOnDelete(); // Responsável
            $table->text('notes')->nullable(); // Notas gerais
            $table->timestamp('status_changed_at')->nullable(); // Quando o estado foi alterado pela última vez
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
