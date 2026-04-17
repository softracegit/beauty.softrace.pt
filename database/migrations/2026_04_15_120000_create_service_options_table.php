<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('duration')->comment('Duração em minutos');
            $table->decimal('price', 10, 2);
            $table->decimal('online_price', 10, 2)->comment('Obrigatório; usado em booking e «desde»');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_baseline')->default(false)->comment('Opção espelho do serviço pai (única por serviço; gerida pela app)');
            $table->timestamps();

            $table->index(['service_id', 'sort_order']);
            $table->index(['service_id', 'is_baseline']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_options');
    }
};
