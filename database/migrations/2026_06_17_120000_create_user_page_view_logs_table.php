<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_page_view_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('route_name', 120)->nullable();
            $table->string('path', 500);
            $table->string('subject_type', 191)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('route_params')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['store_id', 'created_at'], 'user_page_views_store_created_index');
            $table->index(['user_id', 'created_at'], 'user_page_views_user_created_index');
            $table->index(['route_name', 'created_at'], 'user_page_views_route_created_index');
            $table->index(['subject_type', 'subject_id'], 'user_page_views_subject_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_page_view_logs');
    }
};
