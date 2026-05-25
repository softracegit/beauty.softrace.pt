<?php

namespace App\Support;

use App\Models\Agent;
use App\Models\Store;
use Carbon\CarbonImmutable;

/**
 * Estado "Aberto / Quase a fechar / Fechado" para UI pública da loja (resumo + offcanvas).
 */
final class BookingStoreOpenStatus
{
    /**
     * @param  array<string, array{enabled: bool, start: string, end: string}>|null  $storeSchedule
     * @return array{label: string, css_class: string, suffix: string}
     */
    public static function publicUiState(?array $storeSchedule = null): array
    {
        $schedule = $storeSchedule ?? Store::defaultWeeklySchedule();
        $tz = (string) config('booking.business_timezone', config('app.timezone', 'Europe/Lisbon'));
        $now = CarbonImmutable::now($tz);
        $dayKey = WeeklyScheduleWindow::carbonIsoToWeekdayKey((int) $now->isoWeekday());
        $window = WeeklyScheduleWindow::resolveDayWindow($schedule, $dayKey);

        if ($window === null) {
            $nextOpen = self::nextOpenLabel($schedule, $now, $tz);

            return [
                'label' => 'Fechado',
                'css_class' => 'booking-offcanvas__status-closed',
                'suffix' => $nextOpen !== null ? ' · '.$nextOpen : '',
            ];
        }

        $openStr = self::minutesToTimeLabel((int) $window[0]);
        $closeStr = self::minutesToTimeLabel((int) $window[1]);
        $openMinutes = (int) $window[0];
        $closeMinutes = (int) $window[1];
        $currentMinutes = ((int) $now->hour * 60) + (int) $now->minute;
        $isOpenNow = $currentMinutes >= $openMinutes && $currentMinutes < $closeMinutes;
        $minutesToClose = $closeMinutes - $currentMinutes;
        $isClosingSoon = $isOpenNow && $minutesToClose <= 30;
        $closeLabel = 'Fecha às '.$closeStr;
        $openLabel = 'Abre às '.$openStr;

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

        $suffix = $currentMinutes < $openMinutes
            ? ' · '.$openLabel
            : ' · '.$openLabel;

        return [
            'label' => 'Fechado',
            'css_class' => 'booking-offcanvas__status-closed',
            'suffix' => $suffix,
        ];
    }

    /**
     * @param  array<string, array{enabled: bool, start: string, end: string}>  $schedule
     */
    private static function nextOpenLabel(array $schedule, CarbonImmutable $now, string $tz): ?string
    {
        for ($i = 0; $i < 8; $i++) {
            $probe = $now->addDays($i);
            $key = WeeklyScheduleWindow::carbonIsoToWeekdayKey((int) $probe->isoWeekday());
            $window = WeeklyScheduleWindow::resolveDayWindow($schedule, $key);
            if ($window === null) {
                continue;
            }
            $openStr = self::minutesToTimeLabel((int) $window[0]);
            if ($i === 0) {
                return 'Abre às '.$openStr;
            }
            $dayLabel = Agent::weekdayLabels()[$key] ?? $key;

            return 'Abre '.$dayLabel.' às '.$openStr;
        }

        return null;
    }

    private static function minutesToTimeLabel(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return sprintf('%02d:%02d', $h, $m);
    }
}
