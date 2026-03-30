<?php

namespace App\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

final class PhoneDisplay
{
    /**
     * Formato internacional legível (ex.: +351 934 567 890), conforme o país.
     * Números inválidos ou não reconhecidos devolvem o valor original.
     */
    public static function formatInternational(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }
        $phone = trim($phone);
        try {
            $util = PhoneNumberUtil::getInstance();
            $parsed = str_starts_with($phone, '+')
                ? $util->parse($phone, null)
                : $util->parse($phone, 'PT');

            return $util->format($parsed, PhoneNumberFormat::INTERNATIONAL);
        } catch (NumberParseException) {
            return $phone;
        }
    }

    /**
     * URI tel: com dígitos E.164 (adequado a href), ex.: tel:+351934567890
     */
    public static function telHref(?string $phone): string
    {
        if ($phone === null || trim($phone) === '') {
            return '#';
        }
        $phone = trim($phone);
        try {
            $util = PhoneNumberUtil::getInstance();
            $parsed = str_starts_with($phone, '+')
                ? $util->parse($phone, null)
                : $util->parse($phone, 'PT');

            return 'tel:'.$util->format($parsed, PhoneNumberFormat::E164);
        } catch (NumberParseException) {
            $digits = preg_replace('/[^\d+]/', '', $phone);

            return ($digits !== '' && $digits !== '+') ? 'tel:'.$digits : '#';
        }
    }
}
