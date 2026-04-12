<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('desconto', 12, 2)->nullable()->after('gorjeta');
            $table->decimal('valor_pago', 12, 2)->nullable()->after('desconto');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('desconto', 12, 2)->nullable()->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['desconto', 'valor_pago']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('desconto');
        });
    }
};
