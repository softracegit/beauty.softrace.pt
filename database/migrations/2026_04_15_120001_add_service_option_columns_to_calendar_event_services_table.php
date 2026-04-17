<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_event_services', function (Blueprint $table) {
            $table->foreignId('service_option_id')
                ->nullable()
                ->after('service_id')
                ->constrained('service_options')
                ->nullOnDelete()
                ->comment('Catálogo; null em linhas antigas ou se a opção foi removida');
            $table->string('option_name', 255)->nullable()->after('service_option_id')->comment('Snapshot ao agendar');
            $table->unsignedInteger('option_duration')->nullable()->after('option_name')->comment('Snapshot: minutos');
            $table->decimal('option_price', 10, 2)->nullable()->after('option_duration')->comment('Snapshot');
            $table->decimal('option_online_price', 10, 2)->nullable()->after('option_price')->comment('Snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_event_services', function (Blueprint $table) {
            $table->dropForeign(['service_option_id']);
            $table->dropColumn([
                'service_option_id',
                'option_name',
                'option_duration',
                'option_price',
                'option_online_price',
            ]);
        });
    }
};
