<?php

use App\Models\Store;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->json('weekly_schedule')->nullable()->after('timezone');
        });

        $default = Store::defaultWeeklySchedule();

        Store::query()->whereNull('weekly_schedule')->each(function (Store $store) use ($default): void {
            $store->update(['weekly_schedule' => $default]);
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('weekly_schedule');
        });
    }
};
