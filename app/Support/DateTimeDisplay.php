<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;

class DateTimeDisplay
{
    public static function businessTimezone(): string
    {
        return (string) config('booking.business_timezone', 'Europe/Lisbon');
    }

    /**
     * Instante real (UTC na BD) → fuso da loja ou business_timezone.
     * Usar em: created_at, logs, pagamentos, start_at/end_at de CalendarEvent (agenda).
     */
    public static function formatInstant(
        ?DateTimeInterface $dateTime,
        ?int $storeId = null,
        string $format = 'd/m/Y H:i',
        string $fallback = '—',
    ): string {
        $carbon = self::instantInBusinessTimezone($dateTime, $storeId);

        return $carbon instanceof CarbonInterface ? $carbon->format($format) : $fallback;
    }

    /** Alias explícito para horários de marcação vindos do model CalendarEvent. */
    public static function marcacao(
        ?DateTimeInterface $dateTime,
        ?int $storeId = null,
        string $format = 'd/m/Y H:i',
        string $fallback = '—',
    ): string {
        return self::formatInstant($dateTime, $storeId, $format, $fallback);
    }

    /**
     * Valores start_at/end_at do activity log: relógio de parede gravado na coluna (sem offset).
     * Não converter fuso — mostrar a hora literal.
     */
    public static function marcacaoWallClock(
        ?DateTimeInterface $dateTime,
        string $format = 'd/m/Y H:i',
        string $fallback = '—',
    ): string {
        if (! $dateTime) {
            return $fallback;
        }

        return Carbon::instance($dateTime)->utc()->format($format);
    }

    public static function business(?DateTimeInterface $dateTime, string $format = 'd/m/Y H:i', string $fallback = '—'): string
    {
        return self::formatInstant($dateTime, null, $format, $fallback);
    }

    /**
     * Coluna DATE (ex.: data_emissao) — dia calendário sem conversão de fuso para hora.
     */
    public static function businessDate(?DateTimeInterface $dateTime, string $format = 'd/m/Y', string $fallback = '—'): string
    {
        if (! $dateTime) {
            return $fallback;
        }

        return Carbon::instance($dateTime)->format($format);
    }

    public static function inBusiness(?DateTimeInterface $dateTime, ?int $storeId = null): ?CarbonInterface
    {
        return self::instantInBusinessTimezone($dateTime, $storeId);
    }

    public static function timezoneFor(?int $storeId = null): string
    {
        if ($storeId !== null && $storeId > 0) {
            return StoreBusinessTime::timezoneForStore($storeId);
        }

        return self::businessTimezone();
    }

    private static function instantInBusinessTimezone(?DateTimeInterface $dateTime, ?int $storeId = null): ?CarbonInterface
    {
        if (! $dateTime) {
            return null;
        }

        return Carbon::instance($dateTime)->timezone(self::timezoneFor($storeId));
    }
}
