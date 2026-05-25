<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zappy_import_refs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('entity_type', 64);
            $table->string('zappy_key', 255);
            $table->unsignedBigInteger('local_id');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'entity_type', 'zappy_key'], 'zappy_import_refs_store_entity_key_unique');
            $table->index(['store_id', 'entity_type', 'local_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zappy_import_refs');
    }
};
