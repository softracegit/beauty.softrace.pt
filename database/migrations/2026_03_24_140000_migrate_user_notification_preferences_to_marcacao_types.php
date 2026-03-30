<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Preferências passam a ser por tipo de notificação de marcação (assigned, reassigned, …).
     * Remove linhas das categorias genéricas antigas (conta, agendamentos, …).
     */
    public function up(): void
    {
        DB::table('user_notification_preferences')
            ->whereIn('category', ['conta', 'negocio', 'agendamentos', 'vendas', 'clientes', 'equipa'])
            ->delete();
    }

    public function down(): void
    {
        // Irreversível: não restauramos dados de categorias antigas.
    }
};
