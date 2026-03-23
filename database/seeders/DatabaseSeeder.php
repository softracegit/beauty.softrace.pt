<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Administrador com agente associado (middleware has.agent exige agente)
        $user = User::create([
            'name' => 'Administrador',
            'email' => 'admin@imobiliaria.pt',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);

        Agent::create([
            'user_id' => $user->id,
            'name' => 'Administrador',
            'status' => Agent::STATUS_ACTIVE,
        ]);

        // Seeders de referência
        $this->call(PropertyTypeSeeder::class);
        $this->call(PropertyTypologySeeder::class);
        $this->call(LocationSeeder::class);
        $this->call(PropertyFeatureSeeder::class);
        $this->call(TransactionTypeSeeder::class);
        $this->call(PropertyConditionSeeder::class);
        
        // Seeder de clientes
        $this->call(ClientSeeder::class);
    }
}
