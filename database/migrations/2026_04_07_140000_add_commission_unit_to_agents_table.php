<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('commission_unit', 16)->nullable()->after('commission_rate');
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->decimal('commission_rate', 12, 2)->nullable()->change();
        });

        DB::table('agents')->whereNull('commission_unit')->update(['commission_unit' => 'percent']);
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('commission_unit');
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->decimal('commission_rate', 5, 2)->nullable()->change();
        });
    }
};
