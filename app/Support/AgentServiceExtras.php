<?php

namespace App\Support;

use App\Models\Agent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Extra que um técnico pode fazer dentro de um serviço (pivot agent_service_extra).
 * Quem recebe o serviço fica com todos os extras desse serviço até serem desmarcados.
 */
final class AgentServiceExtras
{
    /**
     * @param  list<int>  $previousServiceIds
     * @param  list<int>  $nextServiceIds
     */
    public static function syncForAgent(int $agentId, array $previousServiceIds, array $nextServiceIds): void
    {
        $previous = self::intList($previousServiceIds);
        $next = self::intList($nextServiceIds);
        $removed = array_values(array_diff($previous, $next));
        $added = array_values(array_diff($next, $previous));

        if ($removed !== []) {
            DB::table('agent_service_extra')
                ->where('agent_id', $agentId)
                ->whereIn('service_id', $removed)
                ->delete();
        }

        foreach ($added as $serviceId) {
            self::grantAllExtras($agentId, $serviceId);
        }
    }

    /**
     * @param  list<int>  $previousAgentIds
     * @param  list<int>  $nextAgentIds
     */
    public static function syncForServiceAgents(int $serviceId, array $previousAgentIds, array $nextAgentIds): void
    {
        $previous = self::intList($previousAgentIds);
        $next = self::intList($nextAgentIds);
        $removed = array_values(array_diff($previous, $next));
        $added = array_values(array_diff($next, $previous));

        if ($removed !== []) {
            DB::table('agent_service_extra')
                ->where('service_id', $serviceId)
                ->whereIn('agent_id', $removed)
                ->delete();
        }

        foreach ($added as $agentId) {
            self::grantAllExtras($agentId, $serviceId);
        }
    }

    /**
     * @param  list<int>  $previousExtraIds
     * @param  list<int>  $nextExtraIds
     */
    public static function syncForServiceExtras(int $serviceId, array $previousExtraIds, array $nextExtraIds): void
    {
        $previous = self::intList($previousExtraIds);
        $next = self::intList($nextExtraIds);
        $removed = array_values(array_diff($previous, $next));
        $added = array_values(array_diff($next, $previous));

        if ($removed !== []) {
            DB::table('agent_service_extra')
                ->where('service_id', $serviceId)
                ->whereIn('extra_id', $removed)
                ->delete();
        }

        if ($added === []) {
            return;
        }

        $agentIds = DB::table('agent_service')
            ->where('service_id', $serviceId)
            ->pluck('agent_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $rows = [];
        foreach ($agentIds as $agentId) {
            foreach ($added as $extraId) {
                $rows[] = self::row($agentId, $serviceId, $extraId);
            }
        }
        self::insertRows($rows);
    }

    /**
     * @param  list<int>  $previousServiceIds
     * @param  list<int>  $nextServiceIds
     */
    public static function syncForExtraServices(int $extraId, array $previousServiceIds, array $nextServiceIds): void
    {
        $previous = self::intList($previousServiceIds);
        $next = self::intList($nextServiceIds);
        $removed = array_values(array_diff($previous, $next));
        $added = array_values(array_diff($next, $previous));

        if ($removed !== []) {
            DB::table('agent_service_extra')
                ->where('extra_id', $extraId)
                ->whereIn('service_id', $removed)
                ->delete();
        }

        foreach ($added as $serviceId) {
            $agentIds = DB::table('agent_service')
                ->where('service_id', $serviceId)
                ->pluck('agent_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $rows = [];
            foreach ($agentIds as $agentId) {
                $rows[] = self::row($agentId, $serviceId, $extraId);
            }
            self::insertRows($rows);
        }
    }

    public static function grantAllExtras(int $agentId, int $serviceId): void
    {
        $extraIds = DB::table('service_extra')
            ->where('service_id', $serviceId)
            ->pluck('extra_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $rows = [];
        foreach ($extraIds as $extraId) {
            if ($extraId > 0) {
                $rows[] = self::row($agentId, $serviceId, $extraId);
            }
        }
        self::insertRows($rows);
    }

    /**
     * Substitui, para os técnicos desta loja, os extras marcados no ecrã.
     * Técnicos de outras lojas não são alterados.
     *
     * @param  list<array{service_id: int, extra_id: int}>  $catalogPairs
     * @param  array<int, list<int>>  $agentsByService
     * @param  array<int, array<int, list<int>>>  $wanted
     * @param  list<int>  $storeAgentIds
     */
    public static function replaceStoreSelections(
        array $catalogPairs,
        array $agentsByService,
        array $wanted,
        array $storeAgentIds,
    ): void {
        $storeAgentIds = self::intList($storeAgentIds);
        if ($storeAgentIds === [] || $catalogPairs === []) {
            return;
        }

        foreach ($catalogPairs as $pair) {
            $serviceId = (int) ($pair['service_id'] ?? 0);
            $extraId = (int) ($pair['extra_id'] ?? 0);
            if ($serviceId <= 0 || $extraId <= 0) {
                continue;
            }

            $doesService = array_fill_keys(self::intList($agentsByService[$serviceId] ?? []), true);
            $selected = self::intList($wanted[$serviceId][$extraId] ?? []);
            $selected = array_values(array_filter(
                $selected,
                fn (int $agentId): bool => isset($doesService[$agentId]) && in_array($agentId, $storeAgentIds, true),
            ));

            $delete = DB::table('agent_service_extra')
                ->where('service_id', $serviceId)
                ->where('extra_id', $extraId)
                ->whereIn('agent_id', $storeAgentIds);
            if ($selected !== []) {
                $delete->whereNotIn('agent_id', $selected);
            }
            $delete->delete();

            $rows = [];
            foreach ($selected as $agentId) {
                $rows[] = self::row($agentId, $serviceId, $extraId);
            }
            self::insertRows($rows);
        }
    }

    /**
     * @return array<string, true> chaves "serviceId:extraId"
     */
    public static function allowedPairSet(int $agentId): array
    {
        $set = [];
        $rows = DB::table('agent_service_extra')
            ->where('agent_id', $agentId)
            ->get(['service_id', 'extra_id']);
        foreach ($rows as $row) {
            $set[(int) $row->service_id.':'.(int) $row->extra_id] = true;
        }

        return $set;
    }

    /**
     * @param  list<int>  $agentIds
     * @return array<int, list<array{serviceId: int, extraId: int}>>
     */
    public static function pairsGroupedByAgent(array $agentIds): array
    {
        $agentIds = self::intList($agentIds);
        $grouped = [];
        if ($agentIds === []) {
            return $grouped;
        }

        $rows = DB::table('agent_service_extra')
            ->whereIn('agent_id', $agentIds)
            ->get(['agent_id', 'service_id', 'extra_id']);
        foreach ($rows as $row) {
            $grouped[(int) $row->agent_id][] = [
                'serviceId' => (int) $row->service_id,
                'extraId' => (int) $row->extra_id,
            ];
        }

        return $grouped;
    }

    /**
     * Extras de cada serviço que pelo menos um técnico visível no booking desta loja faz.
     * Com $onlyAgentId, só os extras desse técnico.
     *
     * @return array<int, list<int>>
     */
    public static function bookableExtraIdsByService(int $storeId, ?int $onlyAgentId = null): array
    {
        $agentIds = Agent::query()
            ->where('store_id', $storeId)
            ->where('status', Agent::STATUS_ACTIVE)
            ->where('visible_in_booking', true)
            ->when($onlyAgentId !== null && $onlyAgentId > 0, fn ($q) => $q->whereKey($onlyAgentId))
            ->whereHas('user', fn ($q) => $q->eligibleForPublicBooking())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($agentIds === []) {
            return [];
        }

        $map = [];
        $rows = DB::table('agent_service_extra')
            ->whereIn('agent_id', $agentIds)
            ->get(['service_id', 'extra_id']);
        foreach ($rows as $row) {
            $map[(int) $row->service_id][(int) $row->extra_id] = true;
        }

        $out = [];
        foreach ($map as $serviceId => $extraSet) {
            $out[$serviceId] = array_map('intval', array_keys($extraSet));
        }

        return $out;
    }

    /**
     * @param  Collection<int, Agent>  $agents
     * @param  list<array{service_id: int, extra_id: int}>  $pairs
     * @return Collection<int, Agent>
     */
    public static function filterAgents(Collection $agents, array $pairs): Collection
    {
        $pairs = array_values(array_filter($pairs, function (array $pair): bool {
            return (int) ($pair['service_id'] ?? 0) > 0 && (int) ($pair['extra_id'] ?? 0) > 0;
        }));
        if ($pairs === [] || $agents->isEmpty()) {
            return $agents;
        }

        $grouped = self::pairsGroupedByAgent($agents->pluck('id')->map(fn ($id) => (int) $id)->all());

        return $agents->filter(function (Agent $agent) use ($pairs, $grouped): bool {
            $owned = [];
            foreach ($grouped[(int) $agent->id] ?? [] as $pair) {
                $owned[$pair['serviceId'].':'.$pair['extraId']] = true;
            }
            foreach ($pairs as $pair) {
                $key = (int) $pair['service_id'].':'.(int) $pair['extra_id'];
                if (! isset($owned[$key])) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    /**
     * @param  list<array{service?: mixed, extras?: mixed}>  $bookingLines
     * @return list<array{service_id: int, extra_id: int}>
     */
    public static function pairsFromBookingLines(array $bookingLines): array
    {
        $pairs = [];
        foreach ($bookingLines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $service = $line['service'] ?? null;
            $serviceId = is_object($service) ? (int) ($service->id ?? 0) : (int) ($line['service_id'] ?? 0);
            if ($serviceId <= 0) {
                continue;
            }
            foreach ($line['extras'] ?? [] as $extra) {
                if (! is_array($extra)) {
                    continue;
                }
                $extraId = (int) ($extra['extra_id'] ?? $extra['id'] ?? 0);
                if ($extraId > 0) {
                    $pairs[] = ['service_id' => $serviceId, 'extra_id' => $extraId];
                }
            }
        }

        return $pairs;
    }

    /**
     * @return list<array{service_id: int, extra_id: int}>
     */
    public static function parsePairQuery(string $raw): array
    {
        $pairs = [];
        $seen = [];
        foreach (explode(',', $raw) as $part) {
            $part = trim($part);
            if (! preg_match('/^(\d+):(\d+)$/', $part, $matches)) {
                continue;
            }
            $serviceId = (int) $matches[1];
            $extraId = (int) $matches[2];
            if ($serviceId <= 0 || $extraId <= 0) {
                continue;
            }
            $key = $serviceId.':'.$extraId;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $pairs[] = ['service_id' => $serviceId, 'extra_id' => $extraId];
        }

        return $pairs;
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private static function intList(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $ids),
            fn (int $id): bool => $id > 0,
        )));
    }

    /**
     * @return array{agent_id: int, service_id: int, extra_id: int, created_at: mixed, updated_at: mixed}
     */
    private static function row(int $agentId, int $serviceId, int $extraId): array
    {
        $now = now();

        return [
            'agent_id' => $agentId,
            'service_id' => $serviceId,
            'extra_id' => $extraId,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @param  list<array{agent_id: int, service_id: int, extra_id: int, created_at: mixed, updated_at: mixed}>  $rows
     */
    private static function insertRows(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, 400) as $chunk) {
            DB::table('agent_service_extra')->insertOrIgnore($chunk);
        }
    }
}
