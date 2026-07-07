<?php

use App\Support\ActivityLogMarcacaoOrigin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('calendar_events') || ! Schema::hasColumn('calendar_events', 'marcacao_source')) {
            return;
        }

        DB::table('calendar_events')
            ->where('marcacao_source', ActivityLogMarcacaoOrigin::IMPORT)
            ->update(['marcacao_source' => ActivityLogMarcacaoOrigin::AGENDA]);
    }

    public function down(): void
    {
        // Não é possível distinguir importações Zappy de marcações manuais após normalização.
    }
};
