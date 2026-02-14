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
        Schema::table('leads', function (Blueprint $table) {
            // Adicionar campos de morada seguindo modelo dos Clientes
            $table->string('address')->nullable()->after('phone')->comment('Morada');
            $table->string('door')->nullable()->after('address')->comment('Porta');
            $table->string('floor')->nullable()->after('door')->comment('Andar');
            $table->string('side')->nullable()->after('floor')->comment('Lado');
            $table->string('postal_code')->nullable()->after('side')->comment('Código Postal');
            $table->string('locality')->nullable()->after('postal_code')->comment('Localidade');
            
            // Adicionar campos de localização com IDs (sem foreign keys, apenas índices)
            $table->unsignedInteger('id_district')->nullable()->after('locality');
            $table->unsignedInteger('id_city')->nullable()->after('id_district');
            $table->unsignedInteger('id_parish')->nullable()->after('id_city');
        });

        // Adicionar índices
        Schema::table('leads', function (Blueprint $table) {
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
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['id_district']);
            $table->dropIndex(['id_city']);
            $table->dropIndex(['id_parish']);
            $table->dropColumn([
                'address',
                'door',
                'floor',
                'side',
                'postal_code',
                'locality',
                'id_district',
                'id_city',
                'id_parish'
            ]);
        });
    }
};
