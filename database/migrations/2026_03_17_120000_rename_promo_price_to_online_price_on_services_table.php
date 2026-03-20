<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Bases de dados já existentes tinham a coluna promo_price.
     */
    public function up(): void
    {
        if (Schema::hasColumn('services', 'promo_price') && ! Schema::hasColumn('services', 'online_price')) {
            DB::statement('ALTER TABLE services CHANGE promo_price online_price DECIMAL(10,2) NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('services', 'online_price') && ! Schema::hasColumn('services', 'promo_price')) {
            DB::statement('ALTER TABLE services CHANGE online_price promo_price DECIMAL(10,2) NULL');
        }
    }
};
