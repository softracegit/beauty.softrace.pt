<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('sale_items', 'fee_id')) {
            return;
        }

        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreignId('fee_id')->nullable()->after('extra_id')->constrained('fees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('sale_items', 'fee_id')) {
            return;
        }

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fee_id');
        });
    }
};
