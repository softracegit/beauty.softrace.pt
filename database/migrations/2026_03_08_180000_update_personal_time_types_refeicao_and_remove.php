<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Almoço → Refeição
        DB::table('personal_time_types')
            ->where('name', 'Almoço')
            ->update(['name' => 'Refeição', 'updated_at' => now()]);

        // Remover Outro, Deslocações, Formação (FK nullOnDelete define personal_time_type_id = null nos eventos)
        DB::table('personal_time_types')->whereIn('name', ['Outro', 'Deslocações', 'Formação'])->delete();
    }

    public function down(): void
    {
        // Reinserir os tipos removidos
        $deleted = [
            ['name' => 'Formação', 'icon' => 'ph-graduation-cap', 'duration' => 120, 'sort_order' => 5],
            ['name' => 'Deslocações', 'icon' => 'ph-car', 'duration' => 60, 'sort_order' => 6],
            ['name' => 'Outro', 'icon' => 'ph-dots-three', 'duration' => 60, 'sort_order' => 99],
        ];
        foreach ($deleted as $t) {
            DB::table('personal_time_types')->insert(array_merge($t, [
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
        // Refeição → Almoço
        DB::table('personal_time_types')
            ->where('name', 'Refeição')
            ->update(['name' => 'Almoço', 'updated_at' => now()]);
    }
};
