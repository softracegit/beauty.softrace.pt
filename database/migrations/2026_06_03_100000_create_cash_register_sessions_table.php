<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_register_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('opened_by_user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('opened_at');
            $table->unsignedInteger('opening_float_cents')->default(0);
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->unsignedInteger('closing_cash_counted_cents')->nullable();
            $table->json('closing_summary')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 16)->default('open');
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_register_sessions');
    }
};
