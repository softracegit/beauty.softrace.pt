<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('personal_time_types')) {
            return;
        }

        $typeIds = DB::table('personal_time_types')
            ->where('name', 'Treino')
            ->pluck('id');

        if ($typeIds->isEmpty()) {
            return;
        }

        DB::table('personal_time_types')
            ->whereIn('id', $typeIds)
            ->update([
                'name' => 'Pessoal',
                'icon' => 'ph-user',
                'updated_at' => now(),
            ]);

        if (Schema::hasTable('calendar_events')) {
            DB::table('calendar_events')
                ->whereIn('personal_time_type_id', $typeIds)
                ->where('title', 'Treino')
                ->update(['title' => 'Pessoal']);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('personal_time_types')) {
            return;
        }

        $typeIds = DB::table('personal_time_types')
            ->where('name', 'Pessoal')
            ->where('icon', 'ph-user')
            ->pluck('id');

        if ($typeIds->isEmpty()) {
            return;
        }

        DB::table('personal_time_types')
            ->whereIn('id', $typeIds)
            ->update([
                'name' => 'Treino',
                'icon' => 'ph-barbell',
                'updated_at' => now(),
            ]);

        if (Schema::hasTable('calendar_events')) {
            DB::table('calendar_events')
                ->whereIn('personal_time_type_id', $typeIds)
                ->where('title', 'Pessoal')
                ->update(['title' => 'Treino']);
        }
    }
};
