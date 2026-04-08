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
        Schema::table('calendar_events', function (Blueprint $table) {
            // Filtros principais do endpoint /agenda/events (user/date overlap + status ativo)
            $table->index(['user_id', 'start_at', 'end_at'], 'calendar_events_user_start_end_idx');
            $table->index(['status', 'start_at'], 'calendar_events_status_start_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropIndex('calendar_events_user_start_end_idx');
            $table->dropIndex('calendar_events_status_start_idx');
        });
    }
};
