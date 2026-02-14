<?php

namespace Database\Seeders;

use App\Models\PropertyFeature;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PropertyFeatureSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            ['name' => 'Lareira', 'sort_order' => 1],
            ['name' => 'Mobilado', 'sort_order' => 2],
            ['name' => 'Não Mobilado', 'sort_order' => 3],
            ['name' => 'Garagem', 'sort_order' => 4],
            ['name' => 'Jardim', 'sort_order' => 5],
            ['name' => 'Piscina', 'sort_order' => 6],
            ['name' => 'Varanda', 'sort_order' => 7],
            ['name' => 'Terraço', 'sort_order' => 8],
            ['name' => 'Ar Condicionado', 'sort_order' => 9],
            ['name' => 'Aquecimento Central', 'sort_order' => 10],
            ['name' => 'Elevador', 'sort_order' => 11],
            ['name' => 'Estacionamento', 'sort_order' => 12],
            ['name' => 'Marquise', 'sort_order' => 13],
            ['name' => 'Cozinha Equipada', 'sort_order' => 14],
            ['name' => 'Vista Mar', 'sort_order' => 15],
        ];

        foreach ($features as $feature) {
            PropertyFeature::create([
                'name' => $feature['name'],
                'slug' => Str::slug($feature['name']),
                'is_active' => true,
                'sort_order' => $feature['sort_order'],
            ]);
        }
    }
}
