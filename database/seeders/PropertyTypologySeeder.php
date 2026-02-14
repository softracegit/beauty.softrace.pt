<?php

namespace Database\Seeders;

use App\Models\PropertyTypology;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PropertyTypologySeeder extends Seeder
{
    public function run(): void
    {
        $typologies = [
            ['name' => 'T0', 'bedrooms' => 0, 'sort_order' => 1],
            ['name' => 'T1', 'bedrooms' => 1, 'sort_order' => 2],
            ['name' => 'T2', 'bedrooms' => 2, 'sort_order' => 3],
            ['name' => 'T3', 'bedrooms' => 3, 'sort_order' => 4],
            ['name' => 'T4', 'bedrooms' => 4, 'sort_order' => 5],
            ['name' => 'T5', 'bedrooms' => 5, 'sort_order' => 6],
            ['name' => 'T5+', 'bedrooms' => 6, 'slug' => 't5-plus', 'sort_order' => 7],
        ];

        foreach ($typologies as $typology) {
            PropertyTypology::updateOrCreate(
                ['slug' => $typology['slug'] ?? Str::slug($typology['name'])],
                [
                    'name' => $typology['name'],
                    'bedrooms' => $typology['bedrooms'],
                    'is_active' => true,
                    'sort_order' => $typology['sort_order'],
                ]
            );
        }
    }
}
