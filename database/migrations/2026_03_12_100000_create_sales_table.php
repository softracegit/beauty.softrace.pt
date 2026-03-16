<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_event_id')->nullable()->constrained('calendar_events')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('numero_fatura', 64)->unique();
            $table->date('data_emissao');
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('iva_total', 12, 2)->nullable();
            $table->string('payment_method', 64)->nullable();
            $table->string('status', 32)->default('pago');
            $table->timestamps();

            $table->index(['calendar_event_id']);
            $table->index(['client_id']);
            $table->index(['data_emissao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
