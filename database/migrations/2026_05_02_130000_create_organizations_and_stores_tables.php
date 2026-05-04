<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable()->unique();
            $table->string('nif', 32)->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('email')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('timezone', 64)->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('email')->nullable();
            $table->string('address_line')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
        Schema::dropIfExists('organizations');
    }
};
