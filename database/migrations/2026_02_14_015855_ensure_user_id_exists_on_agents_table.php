<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds user_id to agents if missing (e.g. after deploy where older migrations were not run).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('agents', 'user_id')) {
            Schema::table('agents', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
                $table->unique('user_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('agents', 'user_id')) {
            Schema::table('agents', function (Blueprint $table) {
                $table->dropUnique(['user_id']);
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            });
        }
    }
};
