<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $types = [
            ['name' => 'Refeição', 'icon' => 'ph-cooking-pot', 'duration' => 30, 'sort_order' => 1],
            ['name' => 'Reunião', 'icon' => 'ph-users-three', 'duration' => 60, 'sort_order' => 2],
            ['name' => 'Consulta', 'icon' => 'ph-calendar-check', 'duration' => 60, 'sort_order' => 3],
            ['name' => 'Pessoal', 'icon' => 'ph-user', 'duration' => 60, 'sort_order' => 4],
        ];

        foreach ($types as $t) {
            DB::table('personal_time_types')->insert(array_merge($t, [
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        DB::table('personal_time_types')->truncate();
    }
};
