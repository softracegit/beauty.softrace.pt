<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 26)->unique()->comment('ULID for public API');
            $table->foreignId('calendar_event_id')->nullable()->constrained('calendar_events')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->decimal('total_price', 10, 2);
            $table->decimal('paid_amount', 10, 2)->comment('Deposit charged online');
            $table->decimal('remaining_amount', 10, 2)->comment('Due in store');
            $table->unsignedTinyInteger('deposit_percent_used');
            $table->string('payment_status', 32)->default('pending')->index();
            $table->json('request_payload');
            $table->foreignId('authenticated_booking_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
