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
        Schema::table('opportunities', function (Blueprint $table) {
            $table->foreignId('property_type_id')->nullable()->after('transaction_type_id')->constrained('property_types')->nullOnDelete();
            $table->index('property_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropIndex(['property_type_id']);
            $table->dropForeign(['property_type_id']);
            $table->dropColumn('property_type_id');
        });
    }
};
