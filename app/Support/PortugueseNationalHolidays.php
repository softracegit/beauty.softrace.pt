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
        return array_keys(self::namesByDateBetweenYears($yearStart, $yearEnd));
    }

    /**
     * @return array<string, string> Mapa Y-m-d => nome do feriado.
     */
    public static function namesByDateBetweenYears(int $yearStart, int $yearEnd): array
    {
        if ($yearEnd < $yearStart) {
            return [];
        }

        $map = [];
        for ($y = $yearStart; $y <= $yearEnd; $y++) {
            foreach (self::entriesForYear($y) as $entry) {
                $map[$entry['date']] = $entry['name'];
            }
        }
        ksort($map);

        return $map;
    }

    /**
     * @return array<int, string> Datas Y-m-d dos feriados nacionais para o ano indicado.
     */
    public static function datesForYear(int $year): array
    {
        return array_values(array_map(
            static fn (array $entry): string => $entry['date'],
            self::entriesForYear($year),
        ));
    }

    /**
     * @return list<array{date: string, name: string}>
     */
    public static function entriesForYear(int $year): array
    {
        $easter = self::easterSunday($year);

        return [
            ['date' => Carbon::createMidnightDate($year, 1, 1)->toDateString(), 'name' => 'Ano Novo'],
            ['date' => Carbon::createMidnightDate($year, 4, 25)->toDateString(), 'name' => 'Dia da Liberdade'],
            ['date' => Carbon::createMidnightDate($year, 5, 1)->toDateString(), 'name' => 'Dia do Trabalhador'],
            ['date' => Carbon::createMidnightDate($year, 6, 10)->toDateString(), 'name' => 'Dia de Portugal'],
            ['date' => Carbon::createMidnightDate($year, 8, 15)->toDateString(), 'name' => 'Assunção de Nossa Senhora'],
            ['date' => Carbon::createMidnightDate($year, 10, 5)->toDateString(), 'name' => 'Implantação da República'],
            ['date' => Carbon::createMidnightDate($year, 11, 1)->toDateString(), 'name' => 'Todos-os-Santos'],
            ['date' => Carbon::createMidnightDate($year, 12, 1)->toDateString(), 'name' => 'Restauração da Independência'],
            ['date' => Carbon::createMidnightDate($year, 12, 8)->toDateString(), 'name' => 'Imaculada Conceição'],
            ['date' => Carbon::createMidnightDate($year, 12, 25)->toDateString(), 'name' => 'Natal'],
            ['date' => $easter->copy()->subDays(2)->toDateString(), 'name' => 'Sexta-Feira Santa'],
            ['date' => $easter->copy()->addDays(60)->toDateString(), 'name' => 'Corpo de Deus'],
        ];
    }

    public static function nameFor(CarbonInterface|string $date): ?string
    {
        if ($date instanceof CarbonInterface) {
            $ymd = $date->toDateString();
        } else {
            $ymd = trim($date);
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
                return null;
            }
        }

        $year = (int) substr($ymd, 0, 4);
        foreach (self::entriesForYear($year) as $entry) {
            if ($entry['date'] === $ymd) {
                return $entry['name'];
            }
        }

        return null;
    }

    public static function isHoliday(CarbonInterface|string $date): bool
    {
        return self::nameFor($date) !== null;
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
