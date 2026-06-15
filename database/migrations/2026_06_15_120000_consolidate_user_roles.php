<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Gerente → Administrador; Técnico → Prestador de Serviços.
     */
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'gerente')
            ->update(['role' => 'admin']);

        DB::table('users')
            ->where('role', 'tecnico')
            ->update(['role' => 'prestador']);
    }

    public function down(): void
    {
        // Não reversível com segurança — papéis foram fundidos.
    }
};
