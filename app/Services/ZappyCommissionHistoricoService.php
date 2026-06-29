<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class ZappyCommissionHistoricoService
{
    private const HISTORICAL_END = '2026-05-31';

    /** @var array<string, array<string, array{com_iva: float, sem_iva: float}>>|null */
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

        $desde = (string) ($filters['desde'] ?? '');
        $ate = (string) ($filters['ate'] ?? '');
        if ($desde === '' || $ate === '') {
            return null;
        }

        if ($desde > self::HISTORICAL_END) {
            return null;
        }

        $historicalEnd = min($ate, self::HISTORICAL_END);
        $zappy = $this->zappyTotalsForRange($desde, $historicalEnd, $filters['tecnico'] ?? null);

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
        $techNames = $this->techNamesForFilter($tecnicoFilter);

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

            foreach ($techNames as $name) {
                $month = $totals[$name][$ym] ?? null;
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
     * @return list<string>
     */
    private function techNamesForFilter(mixed $tecnicoFilter): array
    {
        if ($tecnicoFilter !== null && $tecnicoFilter !== '') {
            $userId = (int) $tecnicoFilter;
            $name = $this->zappyNameForUserId($userId);

            return $name !== null ? [$name] : [];
        }

        return array_keys($this->loadTotals());
    }

    private function zappyNameForUserId(int $userId): ?string
    {
        foreach (config('zappy_import.agent_user_map', []) as $name => $id) {
            if ((int) $id === $userId) {
                return (string) $name;
            }
        }

        return null;
    }

    /**
     * @return array<string, array<string, array{com_iva: float, sem_iva: float}>>
     */
    private function loadTotals(): array
    {
        if ($this->totals === null) {
            $this->totals = config('zappy_commission_totals', []);
        }

        return $this->totals;
    }
}
