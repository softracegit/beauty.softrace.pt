<?php

use App\Models\CrmSetting;
use App\Models\Organization;
use App\Models\Store;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dados de marca na organização + herança nas lojas.
 * Limpa lojas extra (dev): mantém a primeira loja da org a herdar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            if (! Schema::hasColumn('organizations', 'timezone')) {
                $table->string('timezone', 64)->nullable()->after('email');
            }
            if (! Schema::hasColumn('organizations', 'weekly_schedule')) {
                $table->json('weekly_schedule')->nullable()->after('timezone');
            }
            if (! Schema::hasColumn('organizations', 'address_line')) {
                $table->string('address_line')->nullable()->after('weekly_schedule');
            }
            if (! Schema::hasColumn('organizations', 'city')) {
                $table->string('city', 120)->nullable()->after('address_line');
            }
            if (! Schema::hasColumn('organizations', 'postal_code')) {
                $table->string('postal_code', 32)->nullable()->after('city');
            }
            if (! Schema::hasColumn('organizations', 'maps_url')) {
                $table->string('maps_url', 512)->nullable()->after('postal_code');
            }
            if (! Schema::hasColumn('organizations', 'website_url')) {
                $table->string('website_url', 512)->nullable()->after('maps_url');
            }
            if (! Schema::hasColumn('organizations', 'instagram_url')) {
                $table->string('instagram_url', 512)->nullable()->after('website_url');
            }
            if (! Schema::hasColumn('organizations', 'logo')) {
                $table->string('logo')->nullable()->after('instagram_url');
            }
            if (! Schema::hasColumn('organizations', 'logo_email')) {
                $table->string('logo_email')->nullable()->after('logo');
            }
            if (! Schema::hasColumn('organizations', 'logo_favicon')) {
                $table->string('logo_favicon')->nullable()->after('logo_email');
            }
        });

        $this->seedOrganizationsFromPrimaryStore();
        $this->promoteInheritableCrmDefaults();
        $this->clearExtraStoresKeepPrimary();
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            foreach ([
                'logo_favicon', 'logo_email', 'logo', 'instagram_url', 'website_url',
                'maps_url', 'postal_code', 'city', 'address_line', 'weekly_schedule', 'timezone',
            ] as $col) {
                if (Schema::hasColumn('organizations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    private function seedOrganizationsFromPrimaryStore(): void
    {
        $orgs = DB::table('organizations')->orderBy('id')->get();
        foreach ($orgs as $org) {
            $store = DB::table('stores')
                ->where('organization_id', $org->id)
                ->orderBy('id')
                ->first();
            if (! $store) {
                continue;
            }

            // Só preenche campos vazios na org (idempotente — não apaga brand já guardado).
            $orgUpdate = ['updated_at' => now()];
            $fillable = [
                'phone', 'email', 'timezone', 'weekly_schedule',
                'address_line', 'city', 'postal_code', 'maps_url',
                'website_url', 'instagram_url', 'logo', 'logo_email', 'logo_favicon',
            ];
            foreach ($fillable as $col) {
                $orgVal = $org->{$col} ?? null;
                $storeVal = $store->{$col} ?? null;
                $orgEmpty = $orgVal === null || $orgVal === '' || $orgVal === '[]';
                $storeFilled = $storeVal !== null && $storeVal !== '' && $storeVal !== '[]';
                if ($orgEmpty && $storeFilled) {
                    $orgUpdate[$col] = $storeVal;
                }
            }

            if (count($orgUpdate) > 1) {
                DB::table('organizations')->where('id', $org->id)->update($orgUpdate);
                $org = (object) array_merge((array) $org, $orgUpdate);
            }

            // Loja principal herda contactos/branding/horário só quando a org ficou com valor.
            $storeNull = [];
            foreach ([
                'phone', 'email', 'timezone', 'weekly_schedule',
                'website_url', 'instagram_url', 'logo', 'logo_email', 'logo_favicon',
            ] as $col) {
                $orgVal = $org->{$col} ?? null;
                $orgFilled = $orgVal !== null && $orgVal !== '' && $orgVal !== '[]';
                if ($orgFilled) {
                    $storeNull[$col] = null;
                }
            }
            if ($storeNull !== []) {
                $storeNull['updated_at'] = now();
                DB::table('stores')->where('id', $store->id)->update($storeNull);
            }
        }
    }

    private function promoteInheritableCrmDefaults(): void
    {
        $keys = [
            CrmSetting::KEY_PRIVACY_LOCK_IDLE_MINUTES,
            CrmSetting::KEY_EMAIL_USE_BUSINESS_BRANDING,
        ];

        $stores = DB::table('stores')->orderBy('id')->get(['id', 'organization_id']);
        $seenOrg = [];
        foreach ($stores as $store) {
            $orgId = (int) $store->organization_id;
            if ($orgId <= 0 || isset($seenOrg[$orgId])) {
                continue;
            }
            $seenOrg[$orgId] = true;

            foreach ($keys as $key) {
                $row = DB::table('crm_settings')
                    ->where('store_id', $store->id)
                    ->where('key', $key)
                    ->orderBy('id')
                    ->first();
                if (! $row) {
                    continue;
                }
                $scope = 'o:'.$orgId.':'.$key;
                $exists = DB::table('crm_settings')->where('setting_scope', $scope)->exists();
                if (! $exists) {
                    DB::table('crm_settings')->insert([
                        'organization_id' => $orgId,
                        'store_id' => null,
                        'key' => $key,
                        'setting_scope' => $scope,
                        'value' => $row->value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                // Remove override da loja principal para passar a herdar.
                DB::table('crm_settings')
                    ->where('store_id', $store->id)
                    ->where('key', $key)
                    ->delete();
            }
        }
    }

    private function clearExtraStoresKeepPrimary(): void
    {
        $orgIds = DB::table('organizations')->pluck('id');
        foreach ($orgIds as $orgId) {
            $primaryId = DB::table('stores')
                ->where('organization_id', $orgId)
                ->orderBy('id')
                ->value('id');
            if (! $primaryId) {
                continue;
            }

            $extras = DB::table('stores')
                ->where('organization_id', $orgId)
                ->where('id', '!=', $primaryId)
                ->pluck('id');

            foreach ($extras as $storeId) {
                $this->forceDeleteEmptyishStore((int) $storeId);
            }
        }
    }

    private function forceDeleteEmptyishStore(int $storeId): void
    {
        // Só remove se não tiver marcações/vendas/clientes/equipa (dados operacionais).
        $blockers = [
            'agents' => DB::table('agents')->where('store_id', $storeId)->exists(),
            'calendar_events' => DB::table('calendar_events')->where('store_id', $storeId)->exists(),
            'clients' => Schema::hasColumn('clients', 'store_id')
                ? DB::table('clients')->where('store_id', $storeId)->exists()
                : false,
            'sales' => DB::table('sales')->where('store_id', $storeId)->exists(),
            'bookings' => Schema::hasTable('bookings')
                ? DB::table('bookings')->where('store_id', $storeId)->exists()
                : false,
        ];
        if (in_array(true, $blockers, true)) {
            return;
        }

        $serviceIds = DB::table('services')->where('store_id', $storeId)->pluck('id');
        if ($serviceIds->isNotEmpty()) {
            DB::table('service_extra')->whereIn('service_id', $serviceIds)->delete();
            DB::table('service_fee')->whereIn('service_id', $serviceIds)->delete();
            if (Schema::hasTable('service_options')) {
                DB::table('service_options')->whereIn('service_id', $serviceIds)->delete();
            }
            DB::table('services')->where('store_id', $storeId)->delete();
        }

        DB::table('categories')->where('store_id', $storeId)->delete();
        if (Schema::hasTable('extra_categories')) {
            $extraCatIds = DB::table('extra_categories')->where('store_id', $storeId)->pluck('id');
            if ($extraCatIds->isNotEmpty() && Schema::hasTable('extras')) {
                DB::table('extras')->whereIn('extra_category_id', $extraCatIds)->delete();
            }
            DB::table('extra_categories')->where('store_id', $storeId)->delete();
        }
        if (Schema::hasTable('fees') && Schema::hasColumn('fees', 'store_id')) {
            DB::table('fees')->where('store_id', $storeId)->delete();
        }
        if (Schema::hasTable('personal_time_types')) {
            DB::table('personal_time_types')->where('store_id', $storeId)->delete();
        }
        DB::table('crm_settings')->where('store_id', $storeId)->delete();
        if (Schema::hasTable('store_user')) {
            DB::table('store_user')->where('store_id', $storeId)->delete();
        }

        DB::table('stores')->where('id', $storeId)->delete();
    }
};
