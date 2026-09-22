<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_mbway_pending_payments', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_payment_intent_id', 255)->unique();
            $table->string('flow', 32); // checkout | deposit
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('calendar_event_id');
            $table->unsignedBigInteger('staff_user_id')->nullable();
            $table->string('status', 32)->default('pending'); // pending | completed | canceled | failed
            $table->json('payload');
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('calendar_event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_mbway_pending_payments');
    }
};
