<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_retention_fee_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->decimal('rate', 12, 2)->nullable();
            $table->string('unit', 16)->nullable();
            $table->date('effective_from');
            $table->timestamps();

            $table->unique(['agent_id', 'effective_from']);
            $table->index(['agent_id', 'effective_from']);
        });

        $agents = DB::table('agents')
            ->whereNotNull('retention_fee_rate')
            ->whereNotNull('retention_fee_effective_from')
            ->get(['id', 'retention_fee_rate', 'retention_fee_unit', 'retention_fee_effective_from']);

        $now = now();
        foreach ($agents as $agent) {
            DB::table('agent_retention_fee_periods')->insert([
                'agent_id' => $agent->id,
                'rate' => $agent->retention_fee_rate,
                'unit' => $agent->retention_fee_unit,
                'effective_from' => $agent->retention_fee_effective_from,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_retention_fee_periods');
    }
};
