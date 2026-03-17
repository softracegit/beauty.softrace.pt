<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Spatie Activity model uses timestamps(); add updated_at if missing.
     */
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            if (! Schema::hasColumn('activity_log', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('activity_log', 'updated_at')) {
            Schema::table('activity_log', function (Blueprint $table) {
                $table->dropColumn('updated_at');
            });
        }
    }
};
