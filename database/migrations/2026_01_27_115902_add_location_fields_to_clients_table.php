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
        // Verificar se os campos já existem antes de adicionar
        if (!Schema::hasColumn('clients', 'id_district')) {
            Schema::table('clients', function (Blueprint $table) {
                // Adicionar campos de localização com IDs (sem foreign keys, apenas índices)
                $table->unsignedInteger('id_district')->nullable()->after('locality');
                $table->unsignedInteger('id_city')->nullable()->after('id_district');
                $table->unsignedInteger('id_parish')->nullable()->after('id_city');
            });

            // Adicionar índices
            Schema::table('clients', function (Blueprint $table) {
                $table->index('id_district');
                $table->index('id_city');
                $table->index('id_parish');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['id_district']);
            $table->dropIndex(['id_city']);
            $table->dropIndex(['id_parish']);
            $table->dropColumn(['id_district', 'id_city', 'id_parish']);
        });
    }
};
