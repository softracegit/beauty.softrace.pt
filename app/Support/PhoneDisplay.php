<?php

namespace App\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumber;

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

    /**
     * Número em E.164 (ex.: +351934567890) para comparações; null se não for possível analisar.
     */
    public static function toE164(?string $phone): ?string
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

            $normalized = self::normalizeBrazilLegacyMobile($util, $parsed);
            if ($normalized !== null) {
                $parsed = $normalized;
            }

            if (! $util->isValidNumber($parsed)) {
                return null;
            }

            return $util->format($parsed, PhoneNumberFormat::E164);
        } catch (NumberParseException) {
            return null;
        }
    }

    /**
     * Números móveis BR antigos (DDD + 8 dígitos começados por 6–9) passaram a ter um 9 extra após o DDD.
     * Ex.: +55 35 9722-2330 → +55 35 99722-2330
     */
    private static function normalizeBrazilLegacyMobile(PhoneNumberUtil $util, PhoneNumber $parsed): ?PhoneNumber
    {
        if ($util->getRegionCodeForNumber($parsed) !== 'BR') {
            return null;
        }

        if ($util->isValidNumber($parsed) && $util->getNumberType($parsed) === PhoneNumberType::MOBILE) {
            return null;
        }

        $national = (string) $parsed->getNationalNumber();
        if (strlen($national) !== 10) {
            return null;
        }

        $subscriber = substr($national, 2);
        if (! preg_match('/^[6789]/', $subscriber)) {
            return null;
        }

        try {
            $candidate = $util->parse('+55'.substr($national, 0, 2).'9'.$subscriber, null);
        } catch (NumberParseException) {
            return null;
        }

        if (! $util->isValidNumber($candidate) || $util->getNumberType($candidate) !== PhoneNumberType::MOBILE) {
            return null;
        }

        return $candidate;
    }
}
