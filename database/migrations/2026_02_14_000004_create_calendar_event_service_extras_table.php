<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_event_service_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_event_service_id')->constrained('calendar_event_services')->cascadeOnDelete();
            $table->foreignId('extra_id')->constrained('extras')->cascadeOnDelete();
            $table->unsignedInteger('duration')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['calendar_event_service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_event_service_extras');
    }
};
