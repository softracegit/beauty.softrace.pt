<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->boolean('visible_in_agenda')->default(true)->after('status');
            $table->boolean('visible_in_booking')->default(true)->after('visible_in_agenda');
            $table->unsignedInteger('agenda_order')->default(0)->after('visible_in_booking');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['visible_in_agenda', 'visible_in_booking', 'agenda_order']);
        });
    }
};
