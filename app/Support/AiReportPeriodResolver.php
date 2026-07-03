<?php

namespace App\Support;

use Carbon\Carbon;

final class AiReportPeriodResolver
{
    /**
     * @return array{desde: string, ate: string, label: string}
     */
    public static function resolve(
        int $storeId,
        ?string $periodo = null,
        ?string $desde = null,
        ?string $ate = null,
    ): array {
        $desde = self::normalizeDate($desde);
        $ate = self::normalizeDate($ate);

        if ($desde !== null && $ate !== null) {
            return [
                'desde' => $desde,
                'ate' => $ate,
                'label' => self::formatRangeLabel($desde, $ate),
            ];
        }

        $now = StoreBusinessTime::nowForStore($storeId);

        return match ($periodo ?? 'mes_passado') {
            'mes_atual' => [
                'desde' => $now->copy()->startOfMonth()->toDateString(),
                'ate' => $now->copy()->endOfMonth()->toDateString(),
                'label' => 'Mês actual',
            ],
            'ultimos_6_meses' => [
                'desde' => $now->copy()->subMonths(5)->startOfMonth()->toDateString(),
                'ate' => $now->copy()->endOfMonth()->toDateString(),
                'label' => 'Últimos 6 meses',
            ],
            default => [
                'desde' => $now->copy()->subMonth()->startOfMonth()->toDateString(),
                'ate' => $now->copy()->subMonth()->endOfMonth()->toDateString(),
                'label' => 'Mês passado',
            ],
        };
    }

    private static function normalizeDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function formatRangeLabel(string $desde, string $ate): string
    {
        return Carbon::parse($desde)->format('d/m/Y').' a '.Carbon::parse($ate)->format('d/m/Y');
    }
}
