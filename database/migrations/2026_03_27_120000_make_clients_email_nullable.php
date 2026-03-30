<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE clients MODIFY email VARCHAR(255) NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('clients')
            ->whereNull('email')
            ->orderBy('id')
            ->chunkById(100, function ($clients) {
                foreach ($clients as $row) {
                    DB::table('clients')->where('id', $row->id)->update([
                        'email' => 'sem-email-'.$row->id.'@placeholder.local',
                    ]);
                }
            });
        DB::statement('ALTER TABLE clients MODIFY email VARCHAR(255) NOT NULL');
    }
};
