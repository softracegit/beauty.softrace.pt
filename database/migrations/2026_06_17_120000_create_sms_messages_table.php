<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('type', 32);
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('client_name')->nullable();
            $table->foreignId('calendar_event_id')->nullable()->constrained('calendar_events')->nullOnDelete();
            $table->string('to_phone', 32);
            $table->string('from_phone', 32);
            $table->text('body');
            $table->string('twilio_sid', 64)->nullable();
            $table->string('twilio_status', 32)->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['store_id', 'sent_at']);
            $table->index(['store_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};
