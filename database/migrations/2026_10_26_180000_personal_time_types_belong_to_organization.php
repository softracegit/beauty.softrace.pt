<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('personal_time_types')) {
            return;
        }

        Schema::table('personal_time_types', function (Blueprint $table) {
            if (! Schema::hasColumn('personal_time_types', 'organization_id')) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('id');
            }
        });

        if (Schema::hasColumn('personal_time_types', 'store_id')) {
            $rows = DB::table('personal_time_types')
                ->whereNotNull('store_id')
                ->select('id', 'store_id', 'name', 'icon', 'duration', 'sort_order', 'is_active')
                ->orderBy('id')
                ->get();

            $storeOrg = DB::table('stores')->pluck('organization_id', 'id');

            foreach ($rows as $row) {
                $orgId = $storeOrg[(int) $row->store_id] ?? null;
                if ($orgId) {
                    DB::table('personal_time_types')->where('id', $row->id)->update([
                        'organization_id' => (int) $orgId,
                    ]);
                }
            }

            // Por org+nome: manter o tipo mais antigo; remapear eventos e apagar duplicados.
            $byOrg = $rows->groupBy(function ($row) use ($storeOrg) {
                return (string) ($storeOrg[(int) $row->store_id] ?? 0);
            });

            foreach ($byOrg as $orgId => $orgRows) {
                if ((int) $orgId <= 0) {
                    continue;
                }

                $byName = $orgRows->groupBy(fn ($r) => mb_strtolower(trim((string) $r->name)));
                foreach ($byName as $group) {
                    $canonical = $group->sortBy('id')->first();
                    $dupIds = $group->pluck('id')->filter(fn ($id) => (int) $id !== (int) $canonical->id)->values()->all();
                    if ($dupIds === []) {
                        continue;
                    }

                    if (Schema::hasTable('calendar_events') && Schema::hasColumn('calendar_events', 'personal_time_type_id')) {
                        DB::table('calendar_events')
                            ->whereIn('personal_time_type_id', $dupIds)
                            ->update(['personal_time_type_id' => (int) $canonical->id]);
                    }

                    DB::table('personal_time_types')->whereIn('id', $dupIds)->delete();
                }
            }

            // Tipos órfãos sem org: tentar apagar se sem eventos; senão deixar nullable.
            $orphans = DB::table('personal_time_types')->whereNull('organization_id')->pluck('id');
            foreach ($orphans as $orphanId) {
                $inUse = Schema::hasTable('calendar_events')
                    && DB::table('calendar_events')->where('personal_time_type_id', $orphanId)->exists();
                if (! $inUse) {
                    DB::table('personal_time_types')->where('id', $orphanId)->delete();
                }
            }
        }

        // Orgs sem tipos: seed defaults.
        if (Schema::hasTable('organizations')) {
            $defaults = [
                ['name' => 'Refeição', 'icon' => 'ph-cooking-pot', 'duration' => 30, 'sort_order' => 1],
                ['name' => 'Reunião', 'icon' => 'ph-users-three', 'duration' => 60, 'sort_order' => 2],
                ['name' => 'Consulta', 'icon' => 'ph-calendar-check', 'duration' => 60, 'sort_order' => 3],
                ['name' => 'Pessoal', 'icon' => 'ph-user', 'duration' => 60, 'sort_order' => 4],
            ];
            $orgIds = DB::table('organizations')->pluck('id');
            $now = now();
            foreach ($orgIds as $orgId) {
                $has = DB::table('personal_time_types')->where('organization_id', $orgId)->exists();
                if ($has) {
                    continue;
                }
                foreach ($defaults as $t) {
                    DB::table('personal_time_types')->insert(array_merge($t, [
                        'organization_id' => (int) $orgId,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]));
                }
            }
        }

        if (Schema::hasColumn('personal_time_types', 'store_id')) {
            Schema::table('personal_time_types', function (Blueprint $table) {
                try {
                    $table->dropForeign(['store_id']);
                } catch (\Throwable) {
                    // SQLite / FK name variants
                }
            });
            Schema::table('personal_time_types', function (Blueprint $table) {
                if (Schema::hasColumn('personal_time_types', 'store_id')) {
                    $table->dropColumn('store_id');
                }
            });
        }

        $nullLeft = DB::table('personal_time_types')->whereNull('organization_id')->count();
        if ($nullLeft === 0 && Schema::hasTable('organizations')) {
            Schema::table('personal_time_types', function (Blueprint $table) {
                $table->index('organization_id');
            });
            try {
                Schema::table('personal_time_types', function (Blueprint $table) {
                    $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
                });
            } catch (\Throwable) {
                // ignore if FK already exists or driver limitation
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('personal_time_types')) {
            return;
        }

        Schema::table('personal_time_types', function (Blueprint $table) {
            if (! Schema::hasColumn('personal_time_types', 'store_id')) {
                $table->unsignedBigInteger('store_id')->nullable()->after('id');
            }
        });

        if (Schema::hasColumn('personal_time_types', 'organization_id') && Schema::hasTable('stores')) {
            $firstStoreByOrg = DB::table('stores')
                ->orderBy('id')
                ->get(['id', 'organization_id'])
                ->groupBy('organization_id')
                ->map(fn ($g) => (int) $g->first()->id);

            foreach (DB::table('personal_time_types')->whereNotNull('organization_id')->get(['id', 'organization_id']) as $row) {
                $storeId = $firstStoreByOrg[(int) $row->organization_id] ?? null;
                if ($storeId) {
                    DB::table('personal_time_types')->where('id', $row->id)->update(['store_id' => $storeId]);
                }
            }
        }

        Schema::table('personal_time_types', function (Blueprint $table) {
            try {
                $table->dropForeign(['organization_id']);
            } catch (\Throwable) {
                // ignore
            }
            if (Schema::hasColumn('personal_time_types', 'organization_id')) {
                $table->dropColumn('organization_id');
            }
        });
    }
};
