<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_event_services', function (Blueprint $table) {
            $table->decimal('original_price', 10, 2)->nullable()->after('price')->comment('Preço original do catálogo ao adicionar; para mostrar riscado se price foi alterado');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_event_services', function (Blueprint $table) {
            $table->dropColumn('original_price');
        });
    }
};
