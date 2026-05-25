<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->integer('amount_cents')->comment('Positive = credit, negative = debit');
            $table->unsignedInteger('balance_after_cents');
            $table->string('type', 40);
            $table->string('idempotency_key', 64)->unique();
            $table->foreignId('calendar_event_id')->nullable()->constrained('calendar_events')->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('description');
            $table->json('metadata')->nullable();
            $table->string('created_by_type', 16)->comment('system, client, staff');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['client_id', 'created_at']);
            $table->index(['store_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_wallet_transactions');
    }
};
