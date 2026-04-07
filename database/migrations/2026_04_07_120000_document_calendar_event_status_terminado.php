<?php

use Illuminate\Database\Migrations\Migration;

/**
 * O campo calendar_events.status é VARCHAR — não requer alteração de esquema.
 * Este ficheiro documenta o novo valor aplicacional «terminado» (CalendarEvent::STATUS_TERMINADO):
 * serviço concluído, ainda sem pagamento, antes de «completo» (faturado).
 */
return new class extends Migration
{
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
