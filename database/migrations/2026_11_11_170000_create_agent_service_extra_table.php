<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_service_extra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('extra_id')->constrained('extras')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['agent_id', 'service_id', 'extra_id']);
            $table->index(['service_id', 'extra_id']);
        });

        $deletedSql = Schema::hasColumn('extras', 'deleted_at') ? ' AND e.deleted_at IS NULL' : '';

        DB::statement("
            INSERT INTO agent_service_extra (agent_id, service_id, extra_id, created_at, updated_at)
            SELECT ags.agent_id, ags.service_id, se.extra_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            FROM agent_service ags
            INNER JOIN service_extra se ON se.service_id = ags.service_id
            INNER JOIN extras e ON e.id = se.extra_id
            WHERE 1 = 1{$deletedSql}
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_service_extra');
    }
};
