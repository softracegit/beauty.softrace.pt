<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo (categories, services, extra_categories, fees) passa a organization_id.
 * Disponibilidade por loja = agent_service com agentes dessa loja.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['categories', 'services', 'extra_categories', 'fees'] as $table) {
            if (! Schema::hasColumn($table, 'organization_id')) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    $blueprint->foreignId('organization_id')
                        ->nullable()
                        ->after('id')
                        ->constrained('organizations')
                        ->restrictOnDelete();
                });
            }
        }

        $this->backfillOrganizationId('categories');
        $this->backfillOrganizationId('services');
        $this->backfillOrganizationId('extra_categories');
        $this->backfillOrganizationId('fees');

        $this->mergeCategories();
        $this->mergeServices();
        $this->mergeExtraCategories();
        $this->mergeExtras();
        $this->mergeFees();

        foreach (['categories', 'services', 'extra_categories', 'fees'] as $table) {
            if (Schema::hasColumn($table, 'store_id') && Schema::getConnection()->getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE {$table} MODIFY store_id BIGINT UNSIGNED NULL");
            }
        }
    }

    public function down(): void
    {
        foreach (['categories', 'services', 'extra_categories', 'fees'] as $table) {
            if (Schema::hasColumn($table, 'organization_id')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropConstrainedForeignId('organization_id');
                });
            }
        }
    }

    private function backfillOrganizationId(string $table): void
    {
        DB::table($table)
            ->whereNull('organization_id')
            ->whereNotNull('store_id')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    $orgId = DB::table('stores')->where('id', $row->store_id)->value('organization_id');
                    if ($orgId) {
                        DB::table($table)->where('id', $row->id)->update(['organization_id' => $orgId]);
                    }
                }
            });
    }

    private function mergeCategories(): void
    {
        $orgIds = DB::table('categories')->whereNotNull('organization_id')->distinct()->pluck('organization_id');

        foreach ($orgIds as $orgId) {
            $rows = DB::table('categories')->where('organization_id', $orgId)->orderBy('id')->get();
            $byName = [];
            foreach ($rows as $row) {
                $key = mb_strtolower(trim((string) $row->name));
                $byName[$key][] = $row;
            }
            foreach ($byName as $bucket) {
                if (count($bucket) < 2) {
                    continue;
                }
                $survivor = $bucket[0];
                foreach (array_slice($bucket, 1) as $dup) {
                    DB::table('services')->where('category_id', $dup->id)->update(['category_id' => $survivor->id]);
                    DB::table('categories')->where('id', $dup->id)->delete();
                }
            }
        }
    }

    private function mergeServices(): void
    {
        $orgIds = DB::table('services')->whereNotNull('organization_id')->distinct()->pluck('organization_id');

        foreach ($orgIds as $orgId) {
            $rows = DB::table('services')->where('organization_id', $orgId)->orderBy('id')->get();
            $byKey = [];
            foreach ($rows as $row) {
                $catName = DB::table('categories')->where('id', $row->category_id)->value('name') ?? '';
                $key = mb_strtolower(trim((string) $catName)).'|'.mb_strtolower(trim((string) $row->name));
                $byKey[$key][] = $row;
            }
            foreach ($byKey as $bucket) {
                if (count($bucket) < 2) {
                    continue;
                }
                $survivor = $bucket[0];
                foreach (array_slice($bucket, 1) as $dup) {
                    $this->repointServiceId((int) $dup->id, (int) $survivor->id);
                    DB::table('services')->where('id', $dup->id)->delete();
                }
            }
        }
    }

    private function repointServiceId(int $fromId, int $toId): void
    {
        if (Schema::hasTable('agent_service')) {
            $agentIds = DB::table('agent_service')->where('service_id', $fromId)->pluck('agent_id');
            foreach ($agentIds as $agentId) {
                $exists = DB::table('agent_service')
                    ->where('agent_id', $agentId)
                    ->where('service_id', $toId)
                    ->exists();
                if (! $exists) {
                    DB::table('agent_service')->insert([
                        'agent_id' => $agentId,
                        'service_id' => $toId,
                    ]);
                }
            }
            DB::table('agent_service')->where('service_id', $fromId)->delete();
        }

        if (Schema::hasTable('service_extra')) {
            $extraIds = DB::table('service_extra')->where('service_id', $fromId)->pluck('extra_id');
            foreach ($extraIds as $extraId) {
                $exists = DB::table('service_extra')
                    ->where('service_id', $toId)
                    ->where('extra_id', $extraId)
                    ->exists();
                if (! $exists) {
                    DB::table('service_extra')->insert([
                        'service_id' => $toId,
                        'extra_id' => $extraId,
                    ]);
                }
            }
            DB::table('service_extra')->where('service_id', $fromId)->delete();
        }

        if (Schema::hasTable('service_fee')) {
            $feeIds = DB::table('service_fee')->where('service_id', $fromId)->pluck('fee_id');
            foreach ($feeIds as $feeId) {
                $exists = DB::table('service_fee')
                    ->where('service_id', $toId)
                    ->where('fee_id', $feeId)
                    ->exists();
                if (! $exists) {
                    DB::table('service_fee')->insert([
                        'service_id' => $toId,
                        'fee_id' => $feeId,
                    ]);
                }
            }
            DB::table('service_fee')->where('service_id', $fromId)->delete();
        }

        if (Schema::hasTable('service_options')) {
            // Keep survivor options; drop dup options (historical CES keep option snapshot columns).
            DB::table('service_options')->where('service_id', $fromId)->delete();
        }

        if (Schema::hasTable('calendar_event_services')) {
            DB::table('calendar_event_services')->where('service_id', $fromId)->update(['service_id' => $toId]);
        }

        if (Schema::hasTable('calendar_events') && Schema::hasColumn('calendar_events', 'service_id')) {
            DB::table('calendar_events')->where('service_id', $fromId)->update(['service_id' => $toId]);
        }
    }

    private function mergeExtraCategories(): void
    {
        $orgIds = DB::table('extra_categories')->whereNotNull('organization_id')->distinct()->pluck('organization_id');

        foreach ($orgIds as $orgId) {
            $rows = DB::table('extra_categories')->where('organization_id', $orgId)->orderBy('id')->get();
            $byName = [];
            foreach ($rows as $row) {
                $key = mb_strtolower(trim((string) $row->name));
                $byName[$key][] = $row;
            }
            foreach ($byName as $bucket) {
                if (count($bucket) < 2) {
                    continue;
                }
                $survivor = $bucket[0];
                foreach (array_slice($bucket, 1) as $dup) {
                    DB::table('extras')->where('extra_category_id', $dup->id)->update(['extra_category_id' => $survivor->id]);
                    DB::table('extra_categories')->where('id', $dup->id)->delete();
                }
            }
        }
    }

    private function mergeExtras(): void
    {
        $rows = DB::table('extras')->orderBy('id')->get();
        $byKey = [];
        foreach ($rows as $row) {
            $key = (int) $row->extra_category_id.'|'.mb_strtolower(trim((string) $row->name));
            $byKey[$key][] = $row;
        }
        foreach ($byKey as $bucket) {
            if (count($bucket) < 2) {
                continue;
            }
            $survivor = $bucket[0];
            foreach (array_slice($bucket, 1) as $dup) {
                if (Schema::hasTable('service_extra')) {
                    $serviceIds = DB::table('service_extra')->where('extra_id', $dup->id)->pluck('service_id');
                    foreach ($serviceIds as $serviceId) {
                        $exists = DB::table('service_extra')
                            ->where('service_id', $serviceId)
                            ->where('extra_id', $survivor->id)
                            ->exists();
                        if (! $exists) {
                            DB::table('service_extra')->insert([
                                'service_id' => $serviceId,
                                'extra_id' => $survivor->id,
                            ]);
                        }
                    }
                    DB::table('service_extra')->where('extra_id', $dup->id)->delete();
                }
                if (Schema::hasTable('calendar_event_service_extras') && Schema::hasColumn('calendar_event_service_extras', 'extra_id')) {
                    DB::table('calendar_event_service_extras')->where('extra_id', $dup->id)->update(['extra_id' => $survivor->id]);
                }
                DB::table('extras')->where('id', $dup->id)->delete();
            }
        }
    }

    private function mergeFees(): void
    {
        $orgIds = DB::table('fees')->whereNotNull('organization_id')->distinct()->pluck('organization_id');

        foreach ($orgIds as $orgId) {
            $rows = DB::table('fees')->where('organization_id', $orgId)->orderBy('id')->get();
            $byName = [];
            foreach ($rows as $row) {
                $key = mb_strtolower(trim((string) $row->name));
                $byName[$key][] = $row;
            }
            foreach ($byName as $bucket) {
                if (count($bucket) < 2) {
                    continue;
                }
                $survivor = $bucket[0];
                foreach (array_slice($bucket, 1) as $dup) {
                    if (Schema::hasTable('service_fee')) {
                        $serviceIds = DB::table('service_fee')->where('fee_id', $dup->id)->pluck('service_id');
                        foreach ($serviceIds as $serviceId) {
                            $exists = DB::table('service_fee')
                                ->where('service_id', $serviceId)
                                ->where('fee_id', $survivor->id)
                                ->exists();
                            if (! $exists) {
                                DB::table('service_fee')->insert([
                                    'service_id' => $serviceId,
                                    'fee_id' => $survivor->id,
                                ]);
                            }
                        }
                        DB::table('service_fee')->where('fee_id', $dup->id)->delete();
                    }
                    DB::table('fees')->where('id', $dup->id)->delete();
                }
            }
        }
    }
};
