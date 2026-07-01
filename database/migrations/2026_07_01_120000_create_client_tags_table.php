<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('color', 7)->default('#bfdbfe');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['store_id', 'name']);
        });

        Schema::create('client_client_tag', function (Blueprint $table) {
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['client_id', 'client_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_client_tag');
        Schema::dropIfExists('client_tags');
    }
};
