<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_auth_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('email', 255)->index();
            $table->string('code_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable()->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('requested_ip', 45)->nullable();
            $table->string('requested_user_agent', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_auth_codes');
    }
};

