<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->timestamp('booking_email_same_day_sent_at')
                ->nullable()
                ->after('booking_email_reminder_failed_at');
            $table->timestamp('booking_email_same_day_failed_at')
                ->nullable()
                ->after('booking_email_same_day_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropColumn([
                'booking_email_same_day_sent_at',
                'booking_email_same_day_failed_at',
            ]);
        });
    }
};
