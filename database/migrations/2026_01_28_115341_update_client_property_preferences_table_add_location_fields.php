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
        Schema::table('client_property_preferences', function (Blueprint $table) {
            // Remover campo priority
            $table->dropColumn('priority');
            
            // Adicionar campos de localização
            $table->unsignedInteger('id_district')->nullable()->after('property_condition_id');
            $table->unsignedInteger('id_city')->nullable()->after('id_district');
            $table->unsignedInteger('id_parish')->nullable()->after('id_city');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_property_preferences', function (Blueprint $table) {
            // Remover campos de localização
            $table->dropColumn(['id_district', 'id_city', 'id_parish']);
            
            // Restaurar campo priority
            $table->integer('priority')->default(0)->after('notes');
        });
    }
};
