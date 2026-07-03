<?php

namespace App\Support;

use Illuminate\Support\Str;

final class ClientNameNormalizer
{
    /**
     * Chave de agrupamento por nome (sem acentos, minúsculas, espaços normalizados).
     * Alinhado com ZappyImportService::clientMatchKey.
     */
    public static function matchKey(string $name): string
    {
        $ascii = Str::ascii(mb_strtolower(trim($name), 'UTF-8'));

        return preg_replace('/\s+/u', ' ', $ascii) ?? $ascii;
    }
}
