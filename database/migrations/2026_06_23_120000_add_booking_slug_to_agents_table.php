<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('agents', 'booking_slug')) {
            Schema::table('agents', function (Blueprint $table) {
                $table->string('booking_slug', 80)->nullable()->after('visible_in_booking');
            });
        }

        if (! $this->indexExists('agents', 'agents_store_id_booking_slug_unique')) {
            Schema::table('agents', function (Blueprint $table) {
                $table->unique(['store_id', 'booking_slug']);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('agents', 'booking_slug')) {
            return;
        }

        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('booking_slug');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $rows = DB::select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$indexName]);

        return $rows !== [];
    }
};
