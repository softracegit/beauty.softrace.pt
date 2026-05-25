<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('logo')->nullable()->after('weekly_schedule');
            $table->string('maps_url', 512)->nullable()->after('logo');
            $table->string('website_url', 512)->nullable()->after('maps_url');
            $table->string('instagram_url', 512)->nullable()->after('website_url');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['logo', 'maps_url', 'website_url', 'instagram_url']);
        });
    }
};
