<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'store_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete();
        });

        $now = now();

        foreach (DB::table('agents')->whereNotNull('user_id')->whereNotNull('store_id')->cursor() as $agent) {
            $orgId = DB::table('stores')->where('id', $agent->store_id)->value('organization_id');
            if ($orgId !== null) {
                DB::table('users')->where('id', $agent->user_id)->update(['organization_id' => $orgId]);
            }

            DB::table('store_user')->updateOrInsert(
                ['user_id' => $agent->user_id, 'store_id' => $agent->store_id],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }

        foreach (DB::table('users')->whereNotNull('client_id')->whereNull('organization_id')->cursor() as $row) {
            $storeId = DB::table('clients')->where('id', $row->client_id)->value('store_id');
            if ($storeId === null) {
                continue;
            }
            $orgId = DB::table('stores')->where('id', $storeId)->value('organization_id');
            if ($orgId !== null) {
                DB::table('users')->where('id', $row->id)->update(['organization_id' => $orgId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::dropIfExists('store_user');
    }
};
