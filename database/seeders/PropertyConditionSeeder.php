<?php

namespace Database\Seeders;

use App\Models\PropertyCondition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PropertyConditionSeeder extends Seeder
{
    public function run(): void
    {
        $conditions = [
            ['name' => 'Novo', 'sort_order' => 1],
            ['name' => 'Usado', 'sort_order' => 2],
            ['name' => 'Em Construção', 'sort_order' => 3],
            ['name' => 'A Requalificar', 'sort_order' => 4],
        ];

        foreach ($conditions as $condition) {
            PropertyCondition::create([
                'name' => $condition['name'],
                'slug' => Str::slug($condition['name']),
                'is_active' => true,
                'sort_order' => $condition['sort_order'],
            ]);
        }
    }
}
