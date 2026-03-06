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
            $table->foreignId('preferred_agent_id')->nullable()->after('avatar')->constrained('agents')->nullOnDelete();
            $table->string('preferred_schedule')->nullable()->after('preferred_agent_id');
            $table->foreignId('preferred_service_id')->nullable()->after('preferred_schedule')->constrained('services')->nullOnDelete();
            $table->text('preferences_notes')->nullable()->after('preferred_service_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['preferred_agent_id']);
            $table->dropForeign(['preferred_service_id']);
            $table->dropColumn(['preferred_agent_id', 'preferred_schedule', 'preferred_service_id', 'preferences_notes']);
        });
    }
};
