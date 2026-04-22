<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('crm_settings')
            ->where('key', 'booking.slot_hold_minutes')
            ->exists();
        if (! $exists) {
            DB::table('crm_settings')->insert([
                'key' => 'booking.slot_hold_minutes',
                'value' => '6',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('crm_settings')
            ->where('key', 'booking.slot_hold_minutes')
            ->delete();
    }
};

