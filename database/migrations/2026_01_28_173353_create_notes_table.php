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
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            // Relacionamento polimórfico - funciona com qualquer modelo
            $table->morphs('notable'); // Cria notable_type e notable_id
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // Quem criou a nota
            $table->enum('type', ['geral', 'email', 'chamada', 'reuniao'])->default('geral'); // Tipo de nota
            $table->text('note'); // Conteúdo da nota
            $table->dateTime('reminder_at')->nullable(); // Data/hora do lembrete
            $table->integer('reminder_advance_minutes')->nullable()->default(15); // Antecedência do lembrete em minutos
            $table->boolean('reminder_sent')->default(false); // Se o lembrete já foi enviado
            $table->timestamps();
            
            // Índices para melhor performance (morphs já cria índice para notable_type e notable_id)
            $table->index('reminder_at');
            $table->index('reminder_sent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
