<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULT_ORG_SLUG = 'default-org';

    private const DEFAULT_STORE_SLUG = 'default';

    public function up(): void
    {
        $now = now();
        $orgId = DB::table('organizations')->insertGetId([
            'name' => 'Organização por defeito',
            'slug' => self::DEFAULT_ORG_SLUG,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $storeId = DB::table('stores')->insertGetId([
            'organization_id' => $orgId,
            'name' => 'Loja por defeito',
            'slug' => self::DEFAULT_STORE_SLUG,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->adjustCrmSettingsUniqueBeforeStoreId();
        $this->adjustClientsUniqueBeforeStoreId();
        $this->adjustSalesUniqueBeforeStoreId();

        $storeScopedTables = [
            'categories',
            'services',
            'extra_categories',
            'agents',
            'calendar_events',
            'clients',
            'crm_settings',
            'bookings',
            'booking_slot_holds',
            'personal_time_types',
            'sales',
            'booking_auth_codes',
        ];

        foreach ($storeScopedTables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            $this->addStoreIdColumn($tableName, $storeId);
        }

        $this->addCrmSettingsCompositeUnique();
        $this->addClientsCompositeUnique();
        $this->addSalesCompositeUnique();
    }

    public function down(): void
    {
        if (! Schema::hasTable('stores')) {
            return;
        }

        $this->dropCompositeUniques();

        $storeScopedTables = [
            'booking_auth_codes',
            'sales',
            'personal_time_types',
            'booking_slot_holds',
            'bookings',
            'crm_settings',
            'clients',
            'calendar_events',
            'agents',
            'extra_categories',
            'services',
            'categories',
        ];

        foreach ($storeScopedTables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'store_id')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $blueprint): void {
                $blueprint->dropForeign(['store_id']);
                $blueprint->dropColumn('store_id');
            });
        }

        $this->restoreCrmSettingsKeyUnique();
        $this->restoreClientsGlobalUnique();
        $this->restoreSalesInvoiceUnique();

        DB::table('stores')->where('slug', self::DEFAULT_STORE_SLUG)->delete();
        DB::table('organizations')->where('slug', self::DEFAULT_ORG_SLUG)->delete();
    }

    private function dropCompositeUniques(): void
    {
        if (Schema::hasTable('crm_settings') && Schema::hasColumn('crm_settings', 'store_id')) {
            Schema::table('crm_settings', function (Blueprint $table): void {
                $table->dropUnique('crm_settings_store_key_unique');
            });
        }

        if (Schema::hasTable('clients') && Schema::hasColumn('clients', 'store_id')) {
            Schema::table('clients', function (Blueprint $table): void {
                $table->dropUnique('clients_store_email_unique');
                $table->dropUnique('clients_store_phone_unique');
            });
        }

        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'store_id')) {
            Schema::table('sales', function (Blueprint $table): void {
                $table->dropUnique('sales_store_numero_fatura_unique');
            });
        }
    }

    private function adjustCrmSettingsUniqueBeforeStoreId(): void
    {
        if (! Schema::hasTable('crm_settings')) {
            return;
        }

        Schema::table('crm_settings', function (Blueprint $table): void {
            $table->dropUnique(['key']);
        });
    }

    private function adjustClientsUniqueBeforeStoreId(): void
    {
        if (! Schema::hasTable('clients')) {
            return;
        }

        Schema::table('clients', function (Blueprint $table): void {
            $table->dropUnique('clients_email_unique');
            $table->dropUnique('clients_phone_unique');
        });
    }

    private function adjustSalesUniqueBeforeStoreId(): void
    {
        if (! Schema::hasTable('sales')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropUnique(['numero_fatura']);
        });
    }

    private function addStoreIdColumn(string $tableName, int $storeId): void
    {
        $driver = Schema::getConnection()->getDriverName();

        Schema::table($tableName, function (Blueprint $blueprint) use ($storeId, $driver): void {
            if ($driver === 'mysql') {
                $blueprint->foreignId('store_id')->after('id')->default($storeId)->constrained('stores')->restrictOnDelete();

                return;
            }

            $blueprint->foreignId('store_id')->default($storeId)->constrained('stores')->restrictOnDelete();
        });

        DB::table($tableName)->whereNull('store_id')->update(['store_id' => $storeId]);
    }

    private function addCrmSettingsCompositeUnique(): void
    {
        if (! Schema::hasTable('crm_settings') || ! Schema::hasColumn('crm_settings', 'store_id')) {
            return;
        }

        Schema::table('crm_settings', function (Blueprint $table): void {
            $table->unique(['store_id', 'key'], 'crm_settings_store_key_unique');
        });
    }

    private function addClientsCompositeUnique(): void
    {
        if (! Schema::hasTable('clients') || ! Schema::hasColumn('clients', 'store_id')) {
            return;
        }

        Schema::table('clients', function (Blueprint $table): void {
            $table->unique(['store_id', 'email'], 'clients_store_email_unique');
            $table->unique(['store_id', 'phone'], 'clients_store_phone_unique');
        });
    }

    private function addSalesCompositeUnique(): void
    {
        if (! Schema::hasTable('sales') || ! Schema::hasColumn('sales', 'store_id')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table): void {
            $table->unique(['store_id', 'numero_fatura'], 'sales_store_numero_fatura_unique');
        });
    }

    private function restoreCrmSettingsKeyUnique(): void
    {
        if (! Schema::hasTable('crm_settings') || Schema::hasColumn('crm_settings', 'store_id')) {
            return;
        }

        Schema::table('crm_settings', function (Blueprint $table): void {
            $table->unique('key');
        });
    }

    private function restoreClientsGlobalUnique(): void
    {
        if (! Schema::hasTable('clients') || Schema::hasColumn('clients', 'store_id')) {
            return;
        }

        Schema::table('clients', function (Blueprint $table): void {
            $table->unique('email', 'clients_email_unique');
            $table->unique('phone', 'clients_phone_unique');
        });
    }

    private function restoreSalesInvoiceUnique(): void
    {
        if (! Schema::hasTable('sales') || Schema::hasColumn('sales', 'store_id')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table): void {
            $table->unique('numero_fatura');
        });
    }
};
