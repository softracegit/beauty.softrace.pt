<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->unsignedInteger('wallet_balance_cents')
                ->default(0)
                ->after('notify_sms_booking_reminders')
                ->comment('Materialized wallet balance; ledger is source of truth');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropColumn('wallet_balance_cents');
        });
    }
};
