<?php

namespace App\Support;

class ClientContactMask
{
    public static function phone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (! is_string($digits) || $digits === '') {
            return '';
        }

        $last3 = substr($digits, -3);

        return '******'.$last3;
    }

    public static function email(?string $email): string
    {
        $email = trim((string) $email);
        if ($email === '' || ! str_contains($email, '@')) {
            return '';
        }

        [$local, $domain] = explode('@', $email, 2);
        if ($local === '' || $domain === '') {
            return '';
        }

        $domainParts = explode('.', $domain);
        $domainHead = (string) ($domainParts[0] ?? '');
        $domainTld = count($domainParts) > 1 ? '.'.implode('.', array_slice($domainParts, 1)) : '';

        $localMasked = substr($local, 0, 1).'***';
        $domainMasked = ($domainHead !== '' ? substr($domainHead, 0, 1) : 'x').'***';

        return $localMasked.'@'.$domainMasked.$domainTld;
    }

    public static function nif(?string $nif): string
    {
        $digits = preg_replace('/\D+/', '', (string) $nif);
        if (! is_string($digits) || $digits === '') {
            return '';
        }

        if (strlen($digits) <= 3) {
            return '***';
        }

        return '***'.substr($digits, -3);
    }
}
