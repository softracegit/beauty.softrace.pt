<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('booking_saved_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('stripe_customer_id', 255);
            $table->string('stripe_payment_method_id', 255)->unique();
            $table->string('brand', 50)->nullable();
            $table->string('last4', 4)->nullable();
            $table->unsignedSmallInteger('exp_month')->nullable();
            $table->unsignedSmallInteger('exp_year')->nullable();
            $table->string('fingerprint', 255)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamp('detached_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'is_default']);
            $table->index(['client_id', 'detached_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_saved_cards');
    }
};
