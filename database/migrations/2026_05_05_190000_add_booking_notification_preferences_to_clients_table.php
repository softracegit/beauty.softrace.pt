<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->boolean('notify_email_booking_updates')->default(true)->after('stripe_customer_id');
            $table->boolean('notify_email_booking_reminders')->default(true)->after('notify_email_booking_updates');
            $table->boolean('notify_sms_booking_reminders')->default(true)->after('notify_email_booking_reminders');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropColumn([
                'notify_email_booking_updates',
                'notify_email_booking_reminders',
                'notify_sms_booking_reminders',
            ]);
        });
    }
};
