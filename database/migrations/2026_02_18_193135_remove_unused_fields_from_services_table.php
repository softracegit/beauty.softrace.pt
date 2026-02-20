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
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn([
                'commission_type',
                'commission_value',
                'is_active',
                'is_visible_online',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->enum('commission_type', ['percent', 'fixed'])->default('percent')->after('promo_price');
            $table->decimal('commission_value', 10, 2)->after('commission_type');
            $table->boolean('is_active')->default(true)->after('commission_value');
            $table->boolean('is_visible_online')->default(false)->after('is_active');
        });
    }
};
