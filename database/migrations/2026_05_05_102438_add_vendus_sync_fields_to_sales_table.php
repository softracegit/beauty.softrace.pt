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
        Schema::table('sales', function (Blueprint $table) {
            $table->string('vendus_sync_status', 32)->nullable()->after('status');
            $table->unsignedBigInteger('vendus_document_id')->nullable()->after('vendus_sync_status');
            $table->timestamp('vendus_synced_at')->nullable()->after('vendus_document_id');
            $table->text('vendus_sync_error')->nullable()->after('vendus_synced_at');

            $table->index(['vendus_sync_status']);
            $table->index(['vendus_document_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['vendus_sync_status']);
            $table->dropIndex(['vendus_document_id']);
            $table->dropColumn([
                'vendus_sync_status',
                'vendus_document_id',
                'vendus_synced_at',
                'vendus_sync_error',
            ]);
        });
    }
};
