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
            // Remover campos antigos de critérios de busca
            if (Schema::hasColumn('opportunities', 'transaction_type_id')) {
                $table->dropForeign(['transaction_type_id']);
                $table->dropColumn('transaction_type_id');
            }
            if (Schema::hasColumn('opportunities', 'property_type_id')) {
                $table->dropForeign(['property_type_id']);
                $table->dropColumn('property_type_id');
            }
            if (Schema::hasColumn('opportunities', 'min_price')) {
                $table->dropColumn('min_price');
            }
            if (Schema::hasColumn('opportunities', 'max_price')) {
                $table->dropColumn('max_price');
            }
            if (Schema::hasColumn('opportunities', 'property_typology_id')) {
                $table->dropForeign(['property_typology_id']);
                $table->dropColumn('property_typology_id');
            }
            if (Schema::hasColumn('opportunities', 'id_district')) {
                $table->dropColumn('id_district');
            }
            if (Schema::hasColumn('opportunities', 'id_city')) {
                $table->dropColumn('id_city');
            }
            if (Schema::hasColumn('opportunities', 'id_parish')) {
                $table->dropColumn('id_parish');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            // Reverter remoção de campos
            $table->foreignId('transaction_type_id')->nullable()->constrained('transaction_types')->onDelete('set null');
            $table->foreignId('property_type_id')->nullable()->constrained('property_types')->onDelete('set null');
            $table->decimal('min_price', 10, 2)->nullable();
            $table->decimal('max_price', 10, 2)->nullable();
            $table->foreignId('property_typology_id')->nullable()->constrained('property_typologies')->onDelete('set null');
            $table->unsignedInteger('id_district')->nullable();
            $table->unsignedInteger('id_city')->nullable();
            $table->unsignedInteger('id_parish')->nullable();
        });
    }
};
