<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->string('cancellation_type', 20)->nullable()->after('cancellation_reason')->comment('faltou, cancelado');
            $table->boolean('refund_reserva')->nullable()->after('cancellation_type');
            $table->boolean('avisou_dentro_prazo')->nullable()->after('refund_reserva');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropColumn(['cancellation_type', 'refund_reserva', 'avisou_dentro_prazo']);
        });
    }
};
