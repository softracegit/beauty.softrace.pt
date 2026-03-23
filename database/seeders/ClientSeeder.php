<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clients = [
            ['name' => 'Maria Silva', 'email' => 'maria.silva@email.pt', 'phone' => '+351 912 345 678', 'locality' => 'Lisboa', 'type' => Client::TYPE_POTENCIAL_CLIENTE],
            ['name' => 'João Santos', 'email' => 'joao.santos@email.pt', 'phone' => '+351 923 456 789', 'locality' => 'Porto', 'type' => Client::TYPE_POTENCIAL_CLIENTE],
            ['name' => 'Ana Ferreira', 'email' => 'ana.ferreira@email.pt', 'phone' => '+351 934 567 890', 'locality' => 'Braga', 'type' => Client::TYPE_POTENCIAL_CLIENTE],
        ];

        foreach ($clients as $data) {
            Client::create($data);
        }
    }
}
