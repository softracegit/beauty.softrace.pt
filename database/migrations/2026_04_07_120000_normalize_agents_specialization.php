<?php

use App\Models\Agent;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normaliza texto livre para chaves de especialização e limpa o campo quando o tipo de membro não aplica.
     */
    public function up(): void
    {
        $rolesWithSpec = User::rolesWithSpecialization();

        DB::table('agents')
            ->whereIn('user_id', function ($q) use ($rolesWithSpec) {
                $q->select('id')->from('users')->whereNotIn('role', $rolesWithSpec);
            })
            ->update(['specialization' => null]);

        $agents = DB::table('agents')->whereNotNull('specialization')->get(['id', 'specialization']);

        foreach ($agents as $row) {
            $normalized = Agent::normalizeLegacySpecialization($row->specialization);
            DB::table('agents')->where('id', $row->id)->update(['specialization' => $normalized]);
        }
    }

    public function down(): void
    {
        // Irreversível: valores antigos em texto livre não podem ser reconstruídos.
    }
};
