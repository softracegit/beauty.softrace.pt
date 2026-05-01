<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
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

        Schema::table('clients', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
