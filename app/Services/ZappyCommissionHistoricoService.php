<?php

namespace App\Services;

use App\Support\TechnicianFilterUserId;
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

        $totals = $this->loadTotals();
        if ($totals === []) {
            return null;
        }

        $desde = $this->normalizeFilterDate((string) ($filters['desde'] ?? ''));
        $ate = $this->normalizeFilterDate((string) ($filters['ate'] ?? ''));
        if ($desde === '' || $ate === '') {
            return null;
        }

        if ($desde > self::HISTORICAL_END) {
            return null;
        }

        $userId = $this->resolveConfigUserId($filters['tecnico'] ?? null, $totals);
        $historicalEnd = min($ate, self::HISTORICAL_END);
        $zappy = $this->zappyTotalsForRange($desde, $historicalEnd, $userId, $totals);

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
     * @param  array<int, array<string, array{com_iva: float, sem_iva: float}>>  $totals
     */
    private function resolveConfigUserId(mixed $tecnicoFilter, array $totals): ?int
    {
        $resolved = TechnicianFilterUserId::resolve($tecnicoFilter);
        if ($resolved !== null && isset($totals[$resolved])) {
            return $resolved;
        }

        if ($tecnicoFilter !== null && $tecnicoFilter !== '') {
            $rawId = (int) $tecnicoFilter;
            if ($rawId > 0 && isset($totals[$rawId])) {
                return $rawId;
            }
        }

        return $resolved;
    }

    private function normalizeFilterDate(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '';
        }

        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $date)) {
            return $date;
        }

        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})#', $date, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return '';
        }
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
     * @param  array<int, array<string, array{com_iva: float, sem_iva: float}>>  $totals
     * @return array{total_comissao_com_iva: float, total_comissao_sem_iva: float}
     */
    private function zappyTotalsForRange(string $desde, string $ate, ?int $userId, array $totals): array
    {
        $userIds = $userId !== null ? [$userId] : array_keys($totals);

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

            foreach ($userIds as $id) {
                $month = $this->monthsForUser($totals, (int) $id)[$ym] ?? null;
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
     * @param  array<int|string, array<string, array{com_iva: float, sem_iva: float}>>  $totals
     * @return array<string, array{com_iva: float, sem_iva: float}>
     */
    private function monthsForUser(array $totals, int $userId): array
    {
        if (isset($totals[$userId]) && is_array($totals[$userId])) {
            return $totals[$userId];
        }

        foreach ($totals as $key => $months) {
            if ((int) $key === $userId && is_array($months)) {
                return $months;
            }
        }

        return [];
    }

    /**
     * @return array<int, array<string, array{com_iva: float, sem_iva: float}>>
     */
    private function loadTotals(): array
    {
        if ($this->totals !== null) {
            return $this->totals;
        }

        $path = config_path('zappy_commission_totals.php');
        if (! is_readable($path)) {
            $this->totals = [];

            return $this->totals;
        }

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($path, true);
        }

        $totals = require $path;
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
        foreach (config('zappy_commission_user_map', []) as $mapName => $id) {
            if (strcasecmp(trim($mapName), trim($name)) === 0) {
                return (int) $id;
            }
        }

        foreach (config('zappy_import.agent_user_map', []) as $mapName => $id) {
            if (strcasecmp(trim($mapName), trim($name)) === 0) {
                return (int) $id;
            }
        }

        return 0;
    }
}
