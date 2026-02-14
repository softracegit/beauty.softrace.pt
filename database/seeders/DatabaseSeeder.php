<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Criar utilizador administrador
        User::create([
            'name' => 'Administrador',
            'email' => 'admin@imobiliaria.pt',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
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
