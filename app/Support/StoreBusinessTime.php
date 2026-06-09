<?php

namespace App\Support;

use App\Models\Store;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;

/**
 * Instantes de tempo alinhados ao fuso da loja (booking) com gravação/comparação em UTC na BD.
 */
class StoreBusinessTime
{
    public static function timezoneForStore(int $storeId): string
    {
        $store = Store::query()->find($storeId);

        return $store?->bookingTimezone() ?? DateTimeDisplay::businessTimezone();
    }

    /** Relógio actual no fuso da loja. */
    public static function nowForStore(int $storeId): CarbonInterface
    {
        return Carbon::now(self::timezoneForStore($storeId));
    }

    /** Mesmo instante que {@see nowForStore}, em UTC (uso em colunas datetime). */
    public static function nowUtcForStore(int $storeId): Carbon
    {
        return self::nowForStore($storeId)->utc();
    }

    /** Converte qualquer instante da BD para UTC, sem reinterpretar o relógio de parede. */
    public static function toUtcInstant(?DateTimeInterface $dateTime): ?Carbon
    {
        if ($dateTime === null) {
            return null;
        }

        return Carbon::instance($dateTime)->utc();
    }

    /**
     * Limite inferior inclusivo para comparações SQL (pequena tolerância a ordem de gravação).
     */
    public static function lowerBoundForQuery(?DateTimeInterface $boundary): ?Carbon
    {
        $utc = self::toUtcInstant($boundary);

        return $utc?->copy()->subSecond();
    }
}
