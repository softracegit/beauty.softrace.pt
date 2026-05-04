<?php

namespace App\Support;

use App\Models\Agent;

/**
 * Janela diária (minutos desde meia-noite) a partir de {@see Agent::$weekly_schedule}.
 *
 * Importante: se o dia tiver só `start`/`end` sem chave `enabled`, assume-se aberto.
 * Usar `empty($v['enabled'])` estava a fechar o dia quando `enabled` nem existia.
 */
final class WeeklyScheduleWindow
{
    public const DEFAULT_OPEN = '09:00';

    public const DEFAULT_CLOSE = '20:00';

    /**
     * @return array{0: int, 1: int}|null [início, fim) em minutos desde meia-noite, ou null se dia desligado / sem janela.
     */
    public static function resolveMinutesWindow(
        ?array $weeklySchedule,
        string $dayKey,
        int $storeStartMin,
        int $storeEndMin,
    ): ?array {
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
            $techStart = $storeStartMin;
            $techEnd = $storeEndMin;
        } else {
            $techStart = Agent::timeStringToMinutes($day['start']);
            $techEnd = Agent::timeStringToMinutes($day['end']);
        }

        $winStart = max($techStart, $storeStartMin);
        $winEnd = min($techEnd, $storeEndMin);
        if ($winStart >= $winEnd) {
            return null;
        }

        return [$winStart, $winEnd];
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
