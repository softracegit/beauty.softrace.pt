<?php

namespace App\Support;

/**
 * Texto seguro para SMS (GSM / sem cedilhas, tildes, etc.).
 */
final class SmsText
{
    /**
     * Remove acentos e caracteres que forçam SMS Unicode (custo/compatibilidade).
     */
    public static function normalize(string $text): string
    {
        $text = strtr($text, [
            'ã' => 'a', 'Ã' => 'A',
            'â' => 'a', 'Â' => 'A',
            'á' => 'a', 'Á' => 'A',
            'ä' => 'a', 'Ä' => 'A',
            'ç' => 'c', 'Ç' => 'C',
            'é' => 'e', 'É' => 'E',
            'ê' => 'e', 'Ê' => 'E',
            'è' => 'e', 'È' => 'E',
            'ë' => 'e', 'Ë' => 'E',
            'í' => 'i', 'Í' => 'I',
            'ì' => 'i', 'Ì' => 'I',
            'ï' => 'i', 'Ï' => 'I',
            'ó' => 'o', 'Ó' => 'O',
            'ô' => 'o', 'Ô' => 'O',
            'õ' => 'o', 'Õ' => 'O',
            'ò' => 'o', 'Ò' => 'O',
            'ö' => 'o', 'Ö' => 'O',
            'ú' => 'u', 'Ú' => 'U',
            'ù' => 'u', 'Ù' => 'U',
            'ü' => 'u', 'Ü' => 'U',
            'ñ' => 'n', 'Ñ' => 'N',
        ]);

        return preg_replace('/[^\x09\x0A\x0D\x20-\x7EàÀ]/u', '', $text) ?? $text;
    }
}
