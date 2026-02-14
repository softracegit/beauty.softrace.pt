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
            // Adicionar novos campos
            $table->string('priority')->default('medium')->after('status')->comment('low, medium, high, urgent');
            $table->string('type')->after('priority')->comment('compra, arrendamento, angariacao');
            
            // Remover campos que não são mais necessários
            if (Schema::hasColumn('opportunities', 'title')) {
                $table->dropColumn('title');
            }
            if (Schema::hasColumn('opportunities', 'description')) {
                $table->dropColumn('description');
            }
            // Não removemos reference aqui porque será gerado automaticamente, mas não será editável
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            // Reverter remoção de campos
            $table->string('title')->after('reference');
            $table->text('description')->nullable()->after('title');
            
            // Remover novos campos
            if (Schema::hasColumn('opportunities', 'priority')) {
                $table->dropColumn('priority');
            }
            if (Schema::hasColumn('opportunities', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
