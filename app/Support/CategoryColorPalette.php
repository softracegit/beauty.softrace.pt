<?php

namespace App\Support;

class CategoryColorPalette
{
    /** @var array<string, string> hex => label */
    public const PALETTE = [
        '#bfdbfe' => 'Azul Céu',
        '#93c5fd' => 'Azul Claro',
        '#a5b4fc' => 'Azul Índigo',
        '#c7d2fe' => 'Azul Lavanda',
        '#ddd6fe' => 'Lavanda',
        '#e9d5ff' => 'Lilás',
        '#f3e8ff' => 'Roxo Pastel',
        '#fbcfe8' => 'Rosa Pastel',
        '#fecdd3' => 'Rosa Claro',
        '#fda4af' => 'Coral Suave',
        '#fed7aa' => 'Laranja Pastel',
        '#fde68a' => 'Âmbar Claro',
        '#fef9c3' => 'Amarelo Pastel',
        '#d9f99d' => 'Verde Lima',
        '#bbf7d0' => 'Verde Menta',
        '#99f6e4' => 'Verde Água',
        '#a5f3fc' => 'Ciano Claro',
        '#bae6fd' => 'Azul Gelo',
    ];

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_keys(self::PALETTE);
    }

    public static function labelFor(?string $hex): string
    {
        $hex = self::normalize($hex);

        return self::PALETTE[$hex] ?? 'Cor';
    }

    public static function normalize(?string $hex): string
    {
        $hex = strtolower(trim((string) $hex));
        if ($hex === '' || ! array_key_exists($hex, self::PALETTE)) {
            return self::default();
        }

        return $hex;
    }

    public static function default(): string
    {
        return '#bfdbfe';
    }

    public static function forName(string $name): string
    {
        $values = self::values();
        $index = abs(crc32(mb_strtolower(trim($name), 'UTF-8'))) % count($values);

        return $values[$index];
    }
}
