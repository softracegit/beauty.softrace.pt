<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Agent;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Associa agents existentes a users existentes pelo email.
     * Se não existir user com o mesmo email, cria um novo user.
     */
    public function up(): void
    {
        // Para cada agent, tentar encontrar ou criar um user correspondente
        $agents = DB::table('agents')->get();
        
        foreach ($agents as $agent) {
            // Tentar encontrar user com o mesmo email
            $user = DB::table('users')->where('email', $agent->email)->first();
            
            if (!$user) {
                // Criar novo user se não existir
                $userId = DB::table('users')->insertGetId([
                    'name' => $agent->name,
                    'email' => $agent->email,
                    'password' => Hash::make('password'), // Password temporário - deve ser alterado no primeiro login
                    'role' => 'consultor', // Default role
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $userId = $user->id;
            }
            
            // Associar agent ao user
            DB::table('agents')
                ->where('id', $agent->id)
                ->update(['user_id' => $userId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remover associações
        DB::table('agents')->update(['user_id' => null]);
    }
};
