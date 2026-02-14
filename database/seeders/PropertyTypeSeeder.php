<?php

namespace Database\Seeders;

use App\Models\PropertyType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PropertyTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Apartamento', 'sort_order' => 1],
            ['name' => 'Moradia', 'sort_order' => 2],
            ['name' => 'Moradia Geminada', 'sort_order' => 3],
            ['name' => 'Quinta', 'sort_order' => 4],
            ['name' => 'Terreno', 'sort_order' => 5],
            ['name' => 'Loja', 'sort_order' => 6],
            ['name' => 'Escritório', 'sort_order' => 7],
            ['name' => 'Armazém', 'sort_order' => 8],
        ];

        foreach ($types as $type) {
            PropertyType::create([
                'name' => $type['name'],
                'slug' => Str::slug($type['name']),
                'is_active' => true,
                'sort_order' => $type['sort_order'],
            ]);
        }
    }
}
