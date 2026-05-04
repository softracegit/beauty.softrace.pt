<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_log')) {
            return;
        }

        Schema::table('activity_log', function (Blueprint $table) {
            if (! Schema::hasColumn('activity_log', 'store_id')) {
                $table->foreignId('store_id')
                    ->nullable()
                    ->after('batch_uuid')
                    ->constrained('stores')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('activity_log', 'organization_id')) {
                $table->foreignId('organization_id')
                    ->nullable()
                    ->after('store_id')
                    ->constrained('organizations')
                    ->nullOnDelete();
            }
        });

        if (Schema::hasColumn('activity_log', 'store_id')
            && ! Schema::hasIndex('activity_log', 'activity_log_store_created_index')) {
            Schema::table('activity_log', function (Blueprint $table) {
                $table->index(['store_id', 'created_at'], 'activity_log_store_created_index');
            });
        }

        if (Schema::hasColumn('activity_log', 'store_id')) {
            $store = \App\Models\Store::query()->where('slug', 'default')->first()
                ?? \App\Models\Store::query()->orderBy('id')->first();
            if ($store !== null) {
                $sid = (int) $store->getKey();
                $oid = $store->organization_id !== null ? (int) $store->organization_id : null;
                $update = ['store_id' => $sid];
                if ($oid !== null) {
                    $update['organization_id'] = $oid;
                }
                DB::table('activity_log')
                    ->whereNull('store_id')
                    ->update($update);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('activity_log')) {
            return;
        }

        if (Schema::hasIndex('activity_log', 'activity_log_store_created_index')) {
            Schema::table('activity_log', function (Blueprint $table) {
                $table->dropIndex('activity_log_store_created_index');
            });
        }

        Schema::table('activity_log', function (Blueprint $table) {
            if (Schema::hasColumn('activity_log', 'store_id')) {
                $table->dropForeign(['store_id']);
                $table->dropColumn('store_id');
            }
            if (Schema::hasColumn('activity_log', 'organization_id')) {
                $table->dropForeign(['organization_id']);
                $table->dropColumn('organization_id');
            }
        });
    }
};
