<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            ['name' => 'Aveiro', 'district' => 'Aveiro', 'county' => 'Aveiro', 'sort_order' => 1],
            ['name' => 'Ílhavo', 'district' => 'Aveiro', 'county' => 'Ílhavo', 'sort_order' => 2],
            ['name' => 'Águeda', 'district' => 'Aveiro', 'county' => 'Águeda', 'sort_order' => 3],
            ['name' => 'Ovar', 'district' => 'Aveiro', 'county' => 'Ovar', 'sort_order' => 4],
            ['name' => 'Espinho', 'district' => 'Aveiro', 'county' => 'Espinho', 'sort_order' => 5],
            ['name' => 'Porto', 'district' => 'Porto', 'county' => 'Porto', 'sort_order' => 6],
            ['name' => 'Lisboa', 'district' => 'Lisboa', 'county' => 'Lisboa', 'sort_order' => 7],
            ['name' => 'Coimbra', 'district' => 'Coimbra', 'county' => 'Coimbra', 'sort_order' => 8],
        ];

        foreach ($locations as $location) {
            Location::create([
                'name' => $location['name'],
                'slug' => Str::slug($location['name']),
                'district' => $location['district'],
                'county' => $location['county'],
                'is_active' => true,
                'sort_order' => $location['sort_order'],
            ]);
        }
    }
}
