<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->date('commission_effective_from')->nullable()->after('commission_unit');
        });

        Schema::create('agent_commission_periods', function (Blueprint $table) {
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
            ->whereNotNull('commission_rate')
            ->get(['id', 'commission_rate', 'commission_unit', 'created_at']);

        $now = now();
        foreach ($agents as $agent) {
            $from = $agent->created_at
                ? substr((string) $agent->created_at, 0, 10)
                : '2020-01-01';

            DB::table('agent_commission_periods')->insert([
                'agent_id' => $agent->id,
                'rate' => $agent->commission_rate,
                'unit' => $agent->commission_unit,
                'effective_from' => $from,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('agents')->where('id', $agent->id)->update([
                'commission_effective_from' => $from,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_commission_periods');

        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('commission_effective_from');
        });
    }
};
