<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_calendar_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('calendar_event_id')->constrained('calendar_events')->cascadeOnDelete();
            $table->unsignedInteger('amount_settled_cents')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['sale_id', 'calendar_event_id'], 'sale_calendar_events_sale_event_unique');
            $table->index(['calendar_event_id', 'is_primary'], 'sale_calendar_events_event_primary_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_calendar_events');
    }
};
