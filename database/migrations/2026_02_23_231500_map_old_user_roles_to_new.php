<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Mapear roles antigos para os novos (Tipo de Membro).
     * diretor -> gerente, consultor -> prestador
     */
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'diretor')
            ->update(['role' => 'gerente']);

        DB::table('users')
            ->where('role', 'consultor')
            ->update(['role' => 'prestador']);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('role', 'gerente')
            ->update(['role' => 'diretor']);

        DB::table('users')
            ->where('role', 'prestador')
            ->update(['role' => 'consultor']);
    }
};
