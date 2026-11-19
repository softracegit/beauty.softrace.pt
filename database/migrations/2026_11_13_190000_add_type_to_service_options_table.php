<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tipo da variante:
     * - choice: escolha única (radio) — define o preço e a duração do serviço;
     * - addon: adicional (interruptor) — soma-se à variante escolhida.
     */
    public function up(): void
    {
        if (Schema::hasColumn('service_options', 'type')) {
            return;
        }

        Schema::table('service_options', function (Blueprint $table) {
            $table->string('type', 16)->default('choice')->after('is_baseline');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('service_options', 'type')) {
            return;
        }

        Schema::table('service_options', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
