<?php

namespace App\Support;

/**
 * Aspeto fixo das etiquetas de cliente (sem cores personalizáveis por etiqueta).
 */
final class ClientTagStyle
{
    public const BACKGROUND = '#dbeafe';

    public const FOREGROUND = '#1e40af';

    public const BORDER = '#bfdbfe';

    public static function defaultColor(): string
    {
        return self::BACKGROUND;
    }
}
