<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->unsignedInteger('wallet_applied_cents')
                ->default(0)
                ->after('paid_amount')
                ->comment('Créditos da carteira aplicados ao depósito desta reserva');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn('wallet_applied_cents');
        });
    }
};
