<?php

use App\Models\Agent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Converte chaves de especialização (select) para o rótulo em texto livre.
     */
    public function up(): void
    {
        $map = Agent::specializations();
        $agents = DB::table('agents')
            ->whereNotNull('specialization')
            ->where('specialization', '!=', '')
            ->get(['id', 'specialization']);

        foreach ($agents as $row) {
            $key = (string) $row->specialization;
            if (! array_key_exists($key, $map)) {
                continue;
            }
            DB::table('agents')->where('id', $row->id)->update([
                'specialization' => $map[$key],
            ]);
        }
    }

    public function down(): void
    {
        $map = array_flip(Agent::specializations());
        $agents = DB::table('agents')
            ->whereNotNull('specialization')
            ->where('specialization', '!=', '')
            ->get(['id', 'specialization']);

        foreach ($agents as $row) {
            $label = (string) $row->specialization;
            if (! array_key_exists($label, $map)) {
                continue;
            }
            DB::table('agents')->where('id', $row->id)->update([
                'specialization' => $map[$label],
            ]);
        }
    }
};
