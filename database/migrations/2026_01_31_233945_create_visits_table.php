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
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained('opportunities')->onDelete('cascade');
            $table->foreignId('property_id')->constrained('properties')->onDelete('cascade');
            $table->dateTime('scheduled_at');
            $table->string('status')->default('agendada'); // agendada, realizada, cancelada
            $table->text('client_feedback_strengths')->nullable()->comment('Pontos fortes do imóvel');
            $table->text('client_feedback_weaknesses')->nullable()->comment('Pontos fracos do imóvel');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['opportunity_id', 'property_id']);
            $table->index('scheduled_at');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
