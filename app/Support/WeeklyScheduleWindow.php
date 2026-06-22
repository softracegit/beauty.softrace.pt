<?php

namespace App\Support;

use App\Models\Agent;
use App\Models\Store;

/**
 * Janela diária (minutos desde meia-noite) a partir de horários semanais (loja ou membro).
 */
final class WeeklyScheduleWindow
{
    public const DEFAULT_OPEN = '09:00';

    public const DEFAULT_CLOSE = '20:00';

    public static function carbonIsoToWeekdayKey(int $isoWeekday): string
    {
        $map = [
            1 => 'mon',
            2 => 'tue',
            3 => 'wed',
            4 => 'thu',
            5 => 'fri',
            6 => 'sat',
            7 => 'sun',
        ];

        return $map[$isoWeekday] ?? 'mon';
    }

    /**
     * Janela do dia para um único horário semanal (loja ou membro).
     *
     * @return array{0: int, 1: int}|null [início, fim) em minutos desde meia-noite
     */
    public static function resolveDayWindow(?array $weeklySchedule, string $dayKey): ?array
    {
        $defaultDay = [
            'enabled' => true,
            'start' => self::DEFAULT_OPEN,
            'end' => self::DEFAULT_CLOSE,
        ];

        if (! is_array($weeklySchedule)) {
            $day = $defaultDay;
        } else {
            $v = $weeklySchedule[$dayKey] ?? null;
            if (! is_array($v)) {
                $day = $defaultDay;
            } else {
                if (array_key_exists('enabled', $v) && ! self::isScheduleDayEnabled($v['enabled'])) {
                    return null;
                }

                $day = [
                    'start' => is_string($v['start'] ?? null) ? $v['start'] : $defaultDay['start'],
                    'end' => is_string($v['end'] ?? null) ? $v['end'] : $defaultDay['end'],
                ];
            }
        }

        $timePattern = '/^([01]\d|2[0-3]):(00|15|30|45)$/';
        if (! preg_match($timePattern, $day['start']) || ! preg_match($timePattern, $day['end'])) {
            return null;
        }

        $start = Agent::timeStringToMinutes($day['start']);
        $end = Agent::timeStringToMinutes($day['end']);
        if ($start >= $end) {
            return null;
        }

        return [$start, $end];
    }

    /**
     * Janela efetiva do membro para marcações: início = max(loja, membro);
     * fim = horário do membro se for depois do fecho da loja, senão interseção.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function resolveMinutesWindow(
        ?array $agentSchedule,
        string $dayKey,
        ?array $storeSchedule = null,
    ): ?array {
        $storeWindow = self::resolveDayWindow(
            $storeSchedule ?? Store::defaultWeeklySchedule(),
            $dayKey
        );
        if ($storeWindow === null) {
            return null;
        }

        $agentWindow = self::resolveDayWindow($agentSchedule, $dayKey);
        if ($agentWindow === null) {
            return null;
        }

        $start = max((int) $storeWindow[0], (int) $agentWindow[0]);
        $storeEnd = (int) $storeWindow[1];
        $agentEnd = (int) $agentWindow[1];
        $end = $agentEnd > $storeEnd ? $agentEnd : min($storeEnd, $agentEnd);
        if ($start >= $end) {
            return null;
        }

        return [$start, $end];
    }

    /**
     * @param  array{0: int, 1: int}  $a
     * @param  array{0: int, 1: int}  $b
     * @return array{0: int, 1: int}|null
     */
    public static function intersectWindows(array $a, array $b): ?array
    {
        $start = max((int) $a[0], (int) $b[0]);
        $end = min((int) $a[1], (int) $b[1]);
        if ($start >= $end) {
            return null;
        }

        return [$start, $end];
    }

    private static function isScheduleDayEnabled(mixed $raw): bool
    {
        if ($raw === false || $raw === 0 || $raw === '0' || $raw === 'false') {
            return false;
        }

        if ($raw === null) {
            return false;
        }

        return true;
    }
}
