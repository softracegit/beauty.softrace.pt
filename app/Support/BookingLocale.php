<?php

namespace App\Support;

use Carbon\Carbon;

final class BookingLocale
{
    public static function supported(): array
    {
        $locales = config('booking.supported_locales', ['pt', 'en']);

        return is_array($locales) ? array_values($locales) : ['pt', 'en'];
    }

    public static function default(): string
    {
        return (string) config('booking.default_locale', 'pt');
    }

    /**
     * Locale for client-facing emails (OTP, confirmação, cancelamento, etc.).
     * UI do booking pode estar em EN; emails mantêm-se em PT por agora.
     */
    public static function emailLocale(): string
    {
        return 'pt';
    }

    public static function resolve(?string $candidate): string
    {
        $locale = is_string($candidate) ? strtolower(trim($candidate)) : '';

        return in_array($locale, self::supported(), true) ? $locale : self::default();
    }

    /**
     * SMS locale rule: +351 → pt, otherwise en.
     */
    public static function fromPhone(?string $phone): string
    {
        $e164 = PhoneDisplay::toE164($phone);
        if ($e164 !== null && str_starts_with($e164, '+351')) {
            return 'pt';
        }

        return 'en';
    }

    public static function apply(string $locale): void
    {
        $resolved = self::resolve($locale);
        app()->setLocale($resolved);
        Carbon::setLocale($resolved);
    }

    public static function intlLocale(?string $locale = null): string
    {
        $resolved = self::resolve($locale ?? app()->getLocale());

        return match ($resolved) {
            'en' => 'en-GB',
            'es' => 'es-ES',
            default => 'pt-PT',
        };
    }
}
