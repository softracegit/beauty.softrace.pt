<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->timestamp('cancellation_evaluated_at')->nullable()->after('avisou_dentro_prazo');
            $table->unsignedSmallInteger('cancellation_notice_hours_applied')->nullable()->after('cancellation_evaluated_at');
            $table->unsignedInteger('wallet_credit_amount_cents')->nullable()->after('cancellation_notice_hours_applied');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->dropColumn([
                'cancellation_evaluated_at',
                'cancellation_notice_hours_applied',
                'wallet_credit_amount_cents',
            ]);
        });
    }
};
