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
        Schema::create('booking_slot_holds', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->string('session_token', 80)->index();
            $table->foreignId('booking_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('selected_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('slot_date');
            $table->dateTime('slot_start_at');
            $table->dateTime('slot_end_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('agent_id_raw', 32)->nullable();
            $table->string('services_signature', 128);
            $table->dateTime('expires_at')->index();
            $table->dateTime('released_at')->nullable()->index();
            $table->string('release_reason', 32)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['selected_user_id', 'slot_start_at', 'slot_end_at'], 'booking_slot_holds_user_slot_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_slot_holds');
    }
};
