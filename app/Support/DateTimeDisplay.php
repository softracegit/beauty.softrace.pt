<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;

class DateTimeDisplay
{
    public static function businessTimezone(): string
    {
        return (string) config('booking.business_timezone', config('app.timezone', 'UTC'));
    }

    public static function business(?DateTimeInterface $dateTime, string $format = 'd/m/Y H:i', string $fallback = '—'): string
    {
        if (! $dateTime) {
            return $fallback;
        }

        return Carbon::instance($dateTime)->timezone(self::businessTimezone())->format($format);
    }

    public static function inBusiness(?DateTimeInterface $dateTime): ?CarbonInterface
    {
        if (! $dateTime) {
            return null;
        }

        return Carbon::instance($dateTime)->timezone(self::businessTimezone());
    }
}
