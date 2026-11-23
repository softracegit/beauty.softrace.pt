<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 20)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'sort_order']);
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->foreignId('material_category_id')
                ->nullable()
                ->after('organization_id')
                ->constrained('material_categories')
                ->cascadeOnDelete();
        });

        $orgIds = DB::table('materials')->distinct()->pluck('organization_id');
        foreach ($orgIds as $orgId) {
            $categoryId = DB::table('material_categories')->insertGetId([
                'organization_id' => $orgId,
                'name' => 'Geral',
                'description' => null,
                'color' => '#bfdbfe',
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('materials')
                ->where('organization_id', $orgId)
                ->whereNull('material_category_id')
                ->update(['material_category_id' => $categoryId]);
        }
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('material_category_id');
        });
        Schema::dropIfExists('material_categories');
    }
};
