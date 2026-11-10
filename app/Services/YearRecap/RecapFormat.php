<?php

namespace App\Services\YearRecap;

use Carbon\CarbonInterface;

/** Formatação dos números e datas mostrados nos slides (pt-PT). */
final class RecapFormat
{
    private const WEEKDAYS_PLURAL = [
        0 => 'Domingos', 1 => 'Segundas', 2 => 'Terças', 3 => 'Quartas',
        4 => 'Quintas', 5 => 'Sextas', 6 => 'Sábados',
    ];

    private const MONTHS = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
        7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
    ];

    public static function int(int|float $n): string
    {
        return number_format((float) $n, 0, ',', ' ');
    }

    public static function money(float $n): string
    {
        return self::int(round($n)).' €';
    }

    /** 148 920 → "148k €" (para os destaques finais). */
    public static function moneyShort(float $n): string
    {
        return $n >= 10000 ? self::int(floor($n / 1000)).'k €' : self::money($n);
    }

    public static function pct(float $ratio): string
    {
        return self::int(round($ratio * 100)).'%';
    }

    public static function signedPct(float $ratio): string
    {
        $v = (int) round($ratio * 100);

        return ($v > 0 ? '+' : '').$v.'%';
    }

    public static function decimal(float $n, int $decimals = 1): string
    {
        return number_format($n, $decimals, ',', ' ');
    }

    /** 75 → "1h 15", 45 → "45 min". */
    public static function duration(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes.' min';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $m > 0 ? sprintf('%dh %02d', $h, $m) : $h.'h';
    }

    public static function weekdayPlural(int $dayOfWeek): string
    {
        return self::WEEKDAYS_PLURAL[$dayOfWeek] ?? '';
    }

    /** "os sábados", "as sextas" */
    public static function weekdayWithArticle(int $dayOfWeek): string
    {
        $article = in_array($dayOfWeek, [0, 6], true) ? 'os' : 'as';

        return $article.' '.mb_strtolower(self::weekdayPlural($dayOfWeek));
    }

    public static function month(int $month): string
    {
        return self::MONTHS[$month] ?? '';
    }

    /** "14 de dezembro" */
    public static function dayMonth(CarbonInterface $date): string
    {
        return $date->day.' de '.mb_strtolower(self::month($date->month));
    }

    /** "Joana Martins Silva" → "Joana S." */
    public static function shortName(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $parts = array_values(array_filter($parts, fn ($p) => $p !== ''));
        if ($parts === []) {
            return '';
        }
        if (count($parts) === 1) {
            return $parts[0];
        }

        return $parts[0].' '.mb_strtoupper(mb_substr(end($parts), 0, 1)).'.';
    }

    /** "x em cada 10" */
    public static function inTen(float $ratio): int
    {
        return max(1, min(10, (int) round($ratio * 10)));
    }
}
