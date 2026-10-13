<?php

namespace App\Support;

/**
 * Regras fiscais PT úteis para faturação (ex.: Vendus exige morada em NIF de empresa).
 */
final class PortugueseFiscalId
{
    /**
     * NIF de pessoa colectiva / organismo (empresa, IPSS, administração, etc.).
     * Dígito inicial 5 = empresas; 6 = administração pública / pessoas colectivas.
     */
    public static function isCompany(?string $nif): bool
    {
        $digits = preg_replace('/\D/', '', (string) $nif);
        if ($digits === null || strlen($digits) !== 9) {
            return false;
        }

        return in_array($digits[0], ['5', '6'], true);
    }

    public static function digits(?string $nif): string
    {
        return (string) preg_replace('/\D/', '', (string) $nif);
    }
}
