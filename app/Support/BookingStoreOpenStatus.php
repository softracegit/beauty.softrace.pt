<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Estado "Aberto / Quase a fechar / Fechado" para UI pública da loja (resumo + offcanvas).
 */
final class BookingStoreOpenStatus
{
    /**
     * @return array{label: string, css_class: string, suffix: string}
     */
    public static function publicUiState(): array
    {
        $tz = (string) config('booking.business_timezone', config('app.timezone', 'Europe/Lisbon'));
        $now = CarbonImmutable::now($tz);
        $openStr = (string) config('booking.public_store.weekday_open', '09:00');
        $closeStr = (string) config('booking.public_store.weekday_close', '20:00');
        $openMinutes = self::timeStringToMinutes($openStr);
        $closeMinutes = self::timeStringToMinutes($closeStr);
        $currentMinutes = ((int) $now->hour * 60) + (int) $now->minute;
        $isWorkingDay = (int) $now->isoWeekday() <= 6;
        $isOpenNow = $isWorkingDay && $currentMinutes >= $openMinutes && $currentMinutes < $closeMinutes;
        $minutesToClose = $closeMinutes - $currentMinutes;
        $isClosingSoon = $isOpenNow && $minutesToClose <= 30;
        $closeLabel = 'Fecha às '.self::normalizeTimeLabel($closeStr);
        $openLabel = 'Abre às '.self::normalizeTimeLabel($openStr);

        if ($isClosingSoon) {
            return [
                'label' => 'Quase a fechar',
                'css_class' => 'booking-offcanvas__status-warning',
                'suffix' => ' · '.$closeLabel,
            ];
        }
        if ($isOpenNow) {
            return [
                'label' => 'Aberto',
                'css_class' => 'booking-offcanvas__status-open',
                'suffix' => ' · '.$closeLabel,
            ];
        }

        $suffix = $isWorkingDay && $currentMinutes < $openMinutes
            ? ' · '.$openLabel
            : ((int) $now->isoWeekday() === 7 ? ' · Domingo encerrado' : ' · '.$openLabel);

        return [
            'label' => 'Fechado',
            'css_class' => 'booking-offcanvas__status-closed',
            'suffix' => $suffix,
        ];
    }

    private static function timeStringToMinutes(string $time): int
    {
        $parts = explode(':', trim($time));
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);

        return ($h * 60) + min(59, max(0, $m));
    }

    private static function normalizeTimeLabel(string $time): string
    {
        return trim($time);
    }
}
