<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales')) {
            return;
        }

        if (! Schema::hasColumn('sales', 'invoice_status')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->string('invoice_status', 32)->default('faturado')->after('status');
                $table->index(['invoice_status']);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('sales') || ! Schema::hasColumn('sales', 'invoice_status')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['invoice_status']);
            $table->dropColumn('invoice_status');
        });
    }
};
