<?php

use App\Models\CrmSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stripe + payments.methods passam a ser por organização (store_id null).
 * setting_scope garante unicidade em MySQL e SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_settings')) {
            return;
        }

        Schema::table('crm_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_settings', 'organization_id')) {
                $table->foreignId('organization_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('organizations')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('crm_settings', 'setting_scope')) {
                $table->string('setting_scope', 191)->nullable()->after('key');
            }
        });

        $existing = DB::table('crm_settings')->whereNotNull('store_id')->orderBy('id')->get(['id', 'store_id', 'key']);
        foreach ($existing as $row) {
            DB::table('crm_settings')->where('id', $row->id)->update([
                'setting_scope' => 's:'.$row->store_id.':'.$row->key,
            ]);
        }

        $this->dropIndexIfExists('crm_settings', 'crm_settings_store_key_unique');

        Schema::table('crm_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable()->change();
        });

        $this->dropIndexIfExists('crm_settings', 'crm_settings_setting_scope_unique');
        Schema::table('crm_settings', function (Blueprint $table) {
            $table->unique('setting_scope', 'crm_settings_setting_scope_unique');
        });

        $this->migratePaymentKeysToOrganization();
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_settings')) {
            return;
        }

        $orgRows = DB::table('crm_settings')
            ->whereNull('store_id')
            ->whereNotNull('organization_id')
            ->get();

        foreach ($orgRows as $row) {
            $storeId = DB::table('stores')
                ->where('organization_id', $row->organization_id)
                ->orderBy('id')
                ->value('id');
            if (! $storeId) {
                DB::table('crm_settings')->where('id', $row->id)->delete();

                continue;
            }
            DB::table('crm_settings')->where('id', $row->id)->update([
                'store_id' => $storeId,
                'organization_id' => null,
                'setting_scope' => 's:'.$storeId.':'.$row->key,
            ]);
        }

        $this->dropIndexIfExists('crm_settings', 'crm_settings_setting_scope_unique');

        Schema::table('crm_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable(false)->change();
        });

        Schema::table('crm_settings', function (Blueprint $table) {
            $table->unique(['store_id', 'key'], 'crm_settings_store_key_unique');
        });

        Schema::table('crm_settings', function (Blueprint $table) {
            if (Schema::hasColumn('crm_settings', 'setting_scope')) {
                $table->dropColumn('setting_scope');
            }
            if (Schema::hasColumn('crm_settings', 'organization_id')) {
                $table->dropConstrainedForeignId('organization_id');
            }
        });
    }

    private function migratePaymentKeysToOrganization(): void
    {
        $orgKeys = CrmSetting::organizationScopedKeys();
        $storeOrgMap = DB::table('stores')->pluck('organization_id', 'id');
        $byOrgKey = [];

        $candidates = DB::table('crm_settings')
            ->whereIn('key', $orgKeys)
            ->whereNotNull('store_id')
            ->orderBy('id')
            ->get();

        foreach ($candidates as $row) {
            $orgId = (int) ($storeOrgMap[$row->store_id] ?? 0);
            if ($orgId <= 0) {
                continue;
            }
            $byOrgKey[$orgId][$row->key][] = $row;
        }

        foreach ($byOrgKey as $orgId => $keys) {
            foreach ($keys as $key => $rows) {
                $winner = $this->pickPreferredPaymentRow($key, $rows);
                $scope = 'o:'.$orgId.':'.$key;

                $existingOrg = DB::table('crm_settings')
                    ->where('setting_scope', $scope)
                    ->first();

                if ($existingOrg) {
                    DB::table('crm_settings')->where('id', $existingOrg->id)->update([
                        'value' => $winner->value,
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('crm_settings')->insert([
                        'organization_id' => $orgId,
                        'store_id' => null,
                        'key' => $key,
                        'setting_scope' => $scope,
                        'value' => $winner->value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $ids = array_map(fn ($r) => $r->id, $rows);
                DB::table('crm_settings')->whereIn('id', $ids)->delete();
            }
        }
    }

    /**
     * @param  list<object>  $rows
     */
    private function pickPreferredPaymentRow(string $key, array $rows): object
    {
        if ($key === CrmSetting::KEY_STRIPE_ENABLED) {
            foreach ($rows as $row) {
                if (in_array(strtolower(trim((string) $row->value)), ['1', 'true', 'yes', 'on'], true)) {
                    return $row;
                }
            }
        }

        if (in_array($key, [
            CrmSetting::KEY_STRIPE_SECRET_KEY,
            CrmSetting::KEY_STRIPE_PUBLISHABLE_KEY,
            CrmSetting::KEY_STRIPE_WEBHOOK_SECRET,
            CrmSetting::KEY_PAYMENT_METHODS,
        ], true)) {
            foreach ($rows as $row) {
                if (trim((string) ($row->value ?? '')) !== '') {
                    return $row;
                }
            }
        }

        return $rows[0];
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
                $blueprint->dropUnique($indexName);
            });
        } catch (\Throwable) {
            // Índice inexistente ou nome diferente no driver.
        }
    }
};
