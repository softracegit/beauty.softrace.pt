<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Feriados nacionais de Portugal (fixas + móveis a partir da Páscoa).
 * Partilhado pela agenda (visual) e pelo Booking (bloqueio de marcações).
 */
final class PortugueseNationalHolidays
{
    /**
     * @return array<int, string> Datas Y-m-d no intervalo de anos (inclusive).
     */
    public static function datesBetweenYears(int $yearStart, int $yearEnd): array
    {
        if ($yearEnd < $yearStart) {
            return [];
        }

        $dates = [];
        for ($y = $yearStart; $y <= $yearEnd; $y++) {
            $dates = array_merge($dates, self::datesForYear($y));
        }

        $dates = array_values(array_unique($dates));
        sort($dates);

        return $dates;
    }

    /**
     * @return array<int, string> Datas Y-m-d dos feriados nacionais para o ano indicado.
     */
    public static function datesForYear(int $year): array
    {
        $easter = self::easterSunday($year);

        return [
            Carbon::createMidnightDate($year, 1, 1)->toDateString(),   // Ano Novo
            Carbon::createMidnightDate($year, 4, 25)->toDateString(),  // Dia da Liberdade
            Carbon::createMidnightDate($year, 5, 1)->toDateString(),   // Dia do Trabalhador
            Carbon::createMidnightDate($year, 6, 10)->toDateString(),  // Dia de Portugal
            Carbon::createMidnightDate($year, 8, 15)->toDateString(),  // Assunção
            Carbon::createMidnightDate($year, 10, 5)->toDateString(),  // Implantação da República
            Carbon::createMidnightDate($year, 11, 1)->toDateString(),  // Todos-os-Santos
            Carbon::createMidnightDate($year, 12, 1)->toDateString(),  // Restauração da Independência
            Carbon::createMidnightDate($year, 12, 8)->toDateString(),  // Imaculada Conceição
            Carbon::createMidnightDate($year, 12, 25)->toDateString(), // Natal
            $easter->copy()->subDays(2)->toDateString(),               // Sexta-Feira Santa
            $easter->copy()->addDays(60)->toDateString(),              // Corpo de Deus
        ];
    }

    public static function isHoliday(CarbonInterface|string $date): bool
    {
        if ($date instanceof CarbonInterface) {
            $ymd = $date->toDateString();
        } else {
            $ymd = trim($date);
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
                return false;
            }
        }

        $year = (int) substr($ymd, 0, 4);

        return in_array($ymd, self::datesForYear($year), true);
    }

    /**
     * Páscoa (algoritmo de Gauss para calendário gregoriano).
     */
    public static function easterSunday(int $year): Carbon
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::createMidnightDate($year, $month, $day);
    }
}
