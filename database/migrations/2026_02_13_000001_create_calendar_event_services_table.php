<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_event_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_event_id')->constrained('calendar_events')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->unsignedInteger('duration')->nullable()->comment('Override em minutos; null = usar do serviço');
            $table->decimal('price', 10, 2)->nullable()->comment('Override; null = usar do serviço');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['calendar_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_event_services');
    }
};
