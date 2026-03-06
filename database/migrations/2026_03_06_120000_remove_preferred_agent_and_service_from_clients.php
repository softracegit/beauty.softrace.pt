<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Técnico e serviço preferidos passam a ser calculados pelas marcações.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['preferred_agent_id']);
            $table->dropForeign(['preferred_service_id']);
            $table->dropColumn(['preferred_agent_id', 'preferred_service_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('preferred_agent_id')->nullable()->after('avatar')->constrained('agents')->nullOnDelete();
            $table->foreignId('preferred_service_id')->nullable()->after('preferred_schedule')->constrained('services')->nullOnDelete();
        });
    }
};
