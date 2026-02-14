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
        Schema::table('clients', function (Blueprint $table) {
            // Remover campos antigos
            $table->dropColumn(['location', 'property_type']);
            
            // Adicionar novos campos de morada
            $table->string('address')->nullable()->after('phone'); // Morada
            $table->string('door')->nullable()->after('address'); // Porta
            $table->string('floor')->nullable()->after('door'); // Andar
            $table->string('side')->nullable()->after('floor'); // Lado
            $table->string('postal_code')->nullable()->after('side'); // Código Postal
            $table->string('locality')->nullable()->after('postal_code'); // Localidade
            
            // Adicionar campos pessoais
            $table->string('nif')->nullable()->after('email'); // NIF
            $table->date('birth_date')->nullable()->after('nif'); // Data de nascimento
            $table->string('gender')->nullable()->after('birth_date'); // Género (M, F, Outro)
            $table->string('nationality')->nullable()->after('gender'); // Nacionalidade
            $table->string('marital_status')->nullable()->after('nationality'); // Estado civil
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Restaurar campos antigos
            $table->string('location')->nullable();
            $table->string('property_type')->nullable();
            
            // Remover novos campos
            $table->dropColumn([
                'address',
                'door',
                'floor',
                'side',
                'postal_code',
                'locality',
                'nif',
                'birth_date',
                'gender',
                'nationality',
                'marital_status'
            ]);
        });
    }
};
