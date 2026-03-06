<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('clients', 'id_city')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->unsignedInteger('id_city')->nullable()->after('id_district');
                $table->index('id_city');
            });
        }
        if (!Schema::hasColumn('clients', 'id_parish')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->unsignedInteger('id_parish')->nullable()->after('id_city');
                $table->index('id_parish');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'id_parish')) {
                $table->dropIndex(['id_parish']);
                $table->dropColumn('id_parish');
            }
            if (Schema::hasColumn('clients', 'id_city')) {
                $table->dropIndex(['id_city']);
                $table->dropColumn('id_city');
            }
        });
    }
};
