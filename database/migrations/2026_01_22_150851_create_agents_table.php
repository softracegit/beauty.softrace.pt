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
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('nif')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender')->nullable(); // M, F, O
            $table->string('nationality')->nullable();
            $table->string('marital_status')->nullable(); // single, married, divorced, widowed, separated
            $table->string('address')->nullable();
            $table->string('door')->nullable();
            $table->string('floor')->nullable();
            $table->string('side')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('locality')->nullable();
            $table->string('specialization')->nullable(); // Especialização
            $table->decimal('commission_rate', 5, 2)->nullable(); // Taxa de comissão (%)
            $table->string('status')->default('active'); // active, inactive, on_leave
            $table->string('avatar')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
