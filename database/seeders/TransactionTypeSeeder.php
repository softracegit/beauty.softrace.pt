<?php

namespace Database\Seeders;

use App\Models\TransactionType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TransactionTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Venda', 'sort_order' => 1],
            ['name' => 'Arrendamento', 'sort_order' => 2],
        ];

        foreach ($types as $type) {
            TransactionType::create([
                'name' => $type['name'],
                'slug' => Str::slug($type['name']),
                'is_active' => true,
                'sort_order' => $type['sort_order'],
            ]);
        }
    }
}
