<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->decimal('retention_fee_rate', 12, 2)->nullable()->after('commission_unit');
            $table->string('retention_fee_unit', 16)->nullable()->after('retention_fee_rate');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['retention_fee_rate', 'retention_fee_unit']);
        });
    }
};
