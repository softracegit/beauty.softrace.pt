<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\Store;
use App\Support\StoreBusinessTime;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MigrateAgentToStoreService
{
    public const MODE_MIGRATE_FUTURE = 'migrate_future';

    public const MODE_REASSIGN = 'reassign';

    /**
     * @return list<string>
     */
    public function conflictMessages(
        Agent $agent,
        Store $targetStore,
        string $mode = self::MODE_MIGRATE_FUTURE,
        ?Agent $takeOverAgent = null,
    ): array {
        $this->assertSameOrganization($agent, $targetStore);
        $this->assertPrestador($agent);

        if ((int) $agent->store_id === (int) $targetStore->id) {
            return ['O membro já pertence a esta loja.'];
        }

        if (! in_array($mode, [self::MODE_MIGRATE_FUTURE, self::MODE_REASSIGN], true)) {
            return ['Opção de marcações futuras inválida.'];
        }

        $sourceStoreId = (int) $agent->store_id;
        $nowUtc = StoreBusinessTime::nowUtcForStore($sourceStoreId);
        $futureEvents = $this->futureEventsQuery($agent, $sourceStoreId, $nowUtc)->get();

        $conflicts = [];

        if ($mode === self::MODE_REASSIGN && $futureEvents->isNotEmpty()) {
            if ($takeOverAgent === null) {
                $conflicts[] = 'Seleccione o membro que fica com as marcações futuras.';
            } else {
                $conflicts = array_merge($conflicts, $this->validateTakeOverAgent($agent, $takeOverAgent));
            }
        }

        // Catálogo é da organização: os mesmos service_id / extras / opções mantêm-se entre lojas.

        return array_values(array_unique($conflicts));
    }

    /**
     * @throws RuntimeException
     */
    public function migrate(
        Agent $agent,
        Store $targetStore,
        string $mode = self::MODE_MIGRATE_FUTURE,
        ?Agent $takeOverAgent = null,
    ): void {
        $conflicts = $this->conflictMessages($agent, $targetStore, $mode, $takeOverAgent);
        if ($conflicts !== []) {
            throw new RuntimeException(implode("\n", $conflicts));
        }

        $sourceStoreId = (int) $agent->store_id;
        $targetStoreId = (int) $targetStore->id;
        $nowUtc = StoreBusinessTime::nowUtcForStore($sourceStoreId);

        DB::transaction(function () use (
            $agent,
            $targetStoreId,
            $sourceStoreId,
            $nowUtc,
            $mode,
            $takeOverAgent,
        ): void {
            $futureEvents = $this->futureEventsQuery($agent, $sourceStoreId, $nowUtc)
                ->lockForUpdate()
                ->get();

            if ($mode === self::MODE_REASSIGN) {
                if ($futureEvents->isNotEmpty() && $takeOverAgent !== null) {
                    foreach ($futureEvents as $event) {
                        $event->update(['user_id' => $takeOverAgent->user_id]);
                    }
                }
            } else {
                foreach ($futureEvents as $event) {
                    // service_id / itens / extras mantêm IDs do catálogo da organização
                    $event->update(['store_id' => $targetStoreId]);
                }
            }

            // agent_service: mesmos service_id (catálogo partilhado na org)
            $serviceIds = $agent->services()->pluck('services.id')->map(fn ($id) => (int) $id)->all();
            $agent->services()->sync($serviceIds);

            $agent->update(['store_id' => $targetStoreId]);

            if ($agent->user) {
                $agent->user->stores()->sync([$targetStoreId]);
            }
        });
    }

    public function futureAppointmentsCount(Agent $agent): int
    {
        $sourceStoreId = (int) $agent->store_id;
        if ($sourceStoreId <= 0) {
            return 0;
        }
        $nowUtc = StoreBusinessTime::nowUtcForStore($sourceStoreId);

        return $this->futureEventsQuery($agent, $sourceStoreId, $nowUtc)->count();
    }

    private function assertPrestador(Agent $agent): void
    {
        $agent->loadMissing('user');
        if (! $agent->user?->isPrestador()) {
            throw new RuntimeException('Só é possível mudar de loja prestadores de serviços.');
        }
    }

    /**
     * @return list<string>
     */
    private function validateTakeOverAgent(Agent $agent, Agent $takeOverAgent): array
    {
        $errors = [];
        $takeOverAgent->loadMissing('user');

        if ((int) $takeOverAgent->id === (int) $agent->id) {
            $errors[] = 'O membro de substituição tem de ser diferente do que está a ser transferido.';
        }
        if ((int) $takeOverAgent->store_id !== (int) $agent->store_id) {
            $errors[] = 'O membro de substituição tem de pertencer à loja actual.';
        }
        if (! $takeOverAgent->user?->isPrestador()) {
            $errors[] = 'O membro de substituição tem de ser prestador de serviços.';
        }
        if (! $takeOverAgent->user_id) {
            $errors[] = 'O membro de substituição não tem utilizador associado.';
        }

        return $errors;
    }

    private function assertSameOrganization(Agent $agent, Store $targetStore): void
    {
        $agent->loadMissing('store');
        if ($agent->store === null) {
            throw new RuntimeException('O membro não tem loja de origem.');
        }
        if ((int) $agent->store->organization_id !== (int) $targetStore->organization_id) {
            throw new RuntimeException('A loja destino tem de pertencer à mesma organização.');
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<CalendarEvent>
     */
    private function futureEventsQuery(Agent $agent, int $sourceStoreId, $nowUtc)
    {
        $terminal = [
            CalendarEvent::STATUS_TERMINADO,
            CalendarEvent::STATUS_FALTOU,
            CalendarEvent::STATUS_CANCELADO,
            CalendarEvent::STATUS_ANULADO,
            CalendarEvent::STATUS_COMPLETO,
        ];

        return CalendarEvent::query()
            ->where('store_id', $sourceStoreId)
            ->where('user_id', $agent->user_id)
            ->where('start_at', '>=', $nowUtc)
            ->where(function ($q) use ($terminal) {
                $q->whereNull('status')->orWhereNotIn('status', $terminal);
            });
    }
}
