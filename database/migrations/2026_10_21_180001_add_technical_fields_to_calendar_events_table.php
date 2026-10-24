<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->text('technical_formula')->nullable()->after('description');
            $table->text('technical_products')->nullable()->after('technical_formula');
            $table->text('technical_notes')->nullable()->after('technical_products');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropColumn(['technical_formula', 'technical_products', 'technical_notes']);
        });
    }
};
