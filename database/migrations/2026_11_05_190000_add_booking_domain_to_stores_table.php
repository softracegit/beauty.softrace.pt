<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('stores', 'booking_domain')) {
            return;
        }

        Schema::table('stores', function (Blueprint $table) {
            $table->string('booking_domain', 255)->nullable()->after('slug');
            $table->index('booking_domain');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('stores', 'booking_domain')) {
            return;
        }

        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex(['booking_domain']);
            $table->dropColumn('booking_domain');
        });
    }
};
