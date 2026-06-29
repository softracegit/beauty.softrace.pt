<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class ZappyCommissionHistoricoService
{
    private const HISTORICAL_END = '2026-05-31';

    /** @var array<int, array<string, array{com_iva: float, sem_iva: float}>>|null */
    private ?array $totals = null;

    /**
     * @param  array{desde?: ?string, ate?: ?string, cliente?: mixed, servico?: mixed, tecnico?: mixed}  $filters
     * @param  Collection<int, object>  $lines
     * @return array{total_comissao_com_iva: float, total_comissao_sem_iva: float}|null
     */
    public function footerTotals(array $filters, Collection $lines): ?array
    {
        if ($this->hasLineLevelFilters($filters)) {
            return null;
        }

        if ($this->loadTotals() === []) {
            return null;
        }

        $desde = (string) ($filters['desde'] ?? '');
        $ate = (string) ($filters['ate'] ?? '');
        if ($desde === '' || $ate === '') {
            return null;
        }

        if ($desde > self::HISTORICAL_END) {
            return null;
        }

        $tecnicoFilter = $filters['tecnico'] ?? null;
        if ($tecnicoFilter !== null && $tecnicoFilter !== '' && ! $this->hasTotalsForUserId((int) $tecnicoFilter)) {
            return null;
        }

        $historicalEnd = min($ate, self::HISTORICAL_END);
        $zappy = $this->zappyTotalsForRange($desde, $historicalEnd, $tecnicoFilter);

        if ($zappy['total_comissao_com_iva'] <= 0 && $zappy['total_comissao_sem_iva'] <= 0) {
            return null;
        }

        if ($ate <= self::HISTORICAL_END) {
            return $zappy;
        }

        $crmFrom = Carbon::parse(self::HISTORICAL_END)->addDay()->toDateString();
        $crmLines = $lines->filter(function (object $line) use ($crmFrom, $ate): bool {
            $date = $line->data_emissao?->format('Y-m-d');

            return $date !== null && $date >= $crmFrom && $date <= $ate;
        });

        return [
            'total_comissao_com_iva' => round($zappy['total_comissao_com_iva'] + (float) $crmLines->sum(fn (object $l) => (float) $l->comissao_com_iva), 2),
            'total_comissao_sem_iva' => round($zappy['total_comissao_sem_iva'] + (float) $crmLines->sum(fn (object $l) => (float) $l->comissao_sem_iva), 2),
        ];
    }

    /**
     * @param  array{desde?: ?string, ate?: ?string, cliente?: mixed, servico?: mixed, tecnico?: mixed}  $filters
     */
    private function hasLineLevelFilters(array $filters): bool
    {
        foreach (['cliente', 'servico'] as $key) {
            $value = $filters[$key] ?? null;
            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{total_comissao_com_iva: float, total_comissao_sem_iva: float}
     */
    private function zappyTotalsForRange(string $desde, string $ate, mixed $tecnicoFilter): array
    {
        $totals = $this->loadTotals();
        $userIds = $this->userIdsForFilter($tecnicoFilter);

        $comIva = 0.0;
        $semIva = 0.0;

        $cursor = Carbon::parse($desde)->startOfMonth();
        $end = Carbon::parse($ate)->endOfMonth();

        while ($cursor->lte($end)) {
            $ym = $cursor->format('Y-m');
            $monthStart = $cursor->copy()->startOfMonth()->toDateString();
            $monthEnd = $cursor->copy()->endOfMonth()->toDateString();

            if ($monthEnd < $desde || $monthStart > $ate) {
                $cursor->addMonth();

                continue;
            }

            if ($monthEnd > self::HISTORICAL_END) {
                $cursor->addMonth();

                continue;
            }

            foreach ($userIds as $userId) {
                $month = $totals[$userId][$ym] ?? null;
                if ($month === null) {
                    continue;
                }
                $comIva += (float) $month['com_iva'];
                $semIva += (float) $month['sem_iva'];
            }

            $cursor->addMonth();
        }

        return [
            'total_comissao_com_iva' => round($comIva, 2),
            'total_comissao_sem_iva' => round($semIva, 2),
        ];
    }

    /**
     * @return list<int>
     */
    private function userIdsForFilter(mixed $tecnicoFilter): array
    {
        if ($tecnicoFilter !== null && $tecnicoFilter !== '') {
            return [(int) $tecnicoFilter];
        }

        return array_map('intval', array_keys($this->loadTotals()));
    }

    private function hasTotalsForUserId(int $userId): bool
    {
        return isset($this->loadTotals()[$userId]);
    }

    /**
     * @return array<int, array<string, array{com_iva: float, sem_iva: float}>>
     */
    private function loadTotals(): array
    {
        if ($this->totals !== null) {
            return $this->totals;
        }

        $totals = config('zappy_commission_totals', []);
        if (! is_array($totals)) {
            $this->totals = [];

            return $this->totals;
        }

        $normalized = [];
        foreach ($totals as $key => $months) {
            if (! is_array($months)) {
                continue;
            }

            $userId = is_numeric((string) $key)
                ? (int) $key
                : $this->userIdForLegacyName((string) $key);

            if ($userId <= 0) {
                continue;
            }

            $normalized[$userId] = $months;
        }

        $this->totals = $normalized;

        return $this->totals;
    }

    private function userIdForLegacyName(string $name): int
    {
        foreach (config('zappy_import.agent_user_map', []) as $mapName => $id) {
            if (strcasecmp(trim($mapName), trim($name)) === 0) {
                return (int) $id;
            }
        }

        return 0;
    }
}
