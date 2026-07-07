<?php

use App\Support\ActivityLogMarcacaoOrigin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('calendar_events')) {
            return;
        }

        Schema::table('calendar_events', function (Blueprint $table) {
            if (! Schema::hasColumn('calendar_events', 'marcacao_source')) {
                $table->string('marcacao_source', 20)->nullable()->after('status');
                $table->index(['store_id', 'marcacao_source'], 'calendar_events_store_marcacao_source_index');
            }
        });

        if (! Schema::hasColumn('calendar_events', 'marcacao_source')) {
            return;
        }

        if (Schema::hasTable('bookings')) {
            DB::table('calendar_events')
                ->where('event_type', 'marcacao')
                ->whereNull('marcacao_source')
                ->whereIn('id', function ($query) {
                    $query->select('calendar_event_id')
                        ->from('bookings')
                        ->whereNotNull('calendar_event_id');
                })
                ->update(['marcacao_source' => ActivityLogMarcacaoOrigin::ONLINE]);
        }

        DB::table('calendar_events')
            ->where('event_type', 'marcacao')
            ->whereNull('marcacao_source')
            ->where('description', 'like', '%[Importado Zappy]%')
            ->update(['marcacao_source' => ActivityLogMarcacaoOrigin::AGENDA]);

        DB::table('calendar_events')
            ->where('event_type', 'marcacao')
            ->whereNull('marcacao_source')
            ->update(['marcacao_source' => ActivityLogMarcacaoOrigin::AGENDA]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('calendar_events') || ! Schema::hasColumn('calendar_events', 'marcacao_source')) {
            return;
        }

        Schema::table('calendar_events', function (Blueprint $table) {
            if (Schema::hasIndex('calendar_events', 'calendar_events_store_marcacao_source_index')) {
                $table->dropIndex('calendar_events_store_marcacao_source_index');
            }
            $table->dropColumn('marcacao_source');
        });
    }
};
