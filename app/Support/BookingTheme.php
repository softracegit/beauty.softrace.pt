<?php

namespace App\Support;

use App\Models\CrmSetting;

/**
 * Temas visuais do booking público (um por loja, via crm_settings).
 */
final class BookingTheme
{
    public const DEFAULT = 'default';

    public const ELEGANT = 'elegant';

    public const NOIR = 'noir';

    public const KEY = CrmSetting::KEY_BOOKING_THEME;

    /** Temas com layout full-width, resumo fixo à direita e passos no conteúdo. */
    public const REFINED_LAYOUT_THEMES = [
        self::ELEGANT,
        self::NOIR,
    ];

    /**
     * @return array<string, array{id: string, label: string, description: string, css: string|array<int, string>|null, fonts: ?string}>
     */
    public static function registry(): array
    {
        return [
            self::DEFAULT => [
                'id' => self::DEFAULT,
                'label' => 'Clássico',
                'description' => 'Layout atual — fundo cinza claro, acento azul e tipografia Plus Jakarta Sans.',
                'css' => null,
                'fonts' => 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap',
            ],
            self::ELEGANT => [
                'id' => self::ELEGANT,
                'label' => 'Elegante',
                'description' => 'Estilo minimalista — fundo creme, acento preto, serif nos títulos e passos numerados.',
                'css' => [
                    'booking-assets/css/themes/refined.css',
                    'booking-assets/css/themes/elegant.css',
                ],
                'fonts' => 'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500;1,600&family=Inter:wght@400;500;600;700&display=swap',
            ],
            self::NOIR => [
                'id' => self::NOIR,
                'label' => 'Noir',
                'description' => 'Tema escuro minimalista — fundo quase preto, acentos lilás e azul, tipografia sans-serif.',
                'css' => [
                    'booking-assets/css/themes/refined.css',
                    'booking-assets/css/themes/noir.css',
                ],
                'fonts' => 'https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,500&display=swap',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return array_map(
            static fn (array $theme): string => $theme['label'],
            self::registry(),
        );
    }

    public static function resolve(?string $themeId = null, ?int $storeId = null): string
    {
        $id = $themeId ?? CrmSetting::bookingTheme($storeId);

        return array_key_exists($id, self::registry()) ? $id : self::DEFAULT;
    }

    public static function usesRefinedLayout(?string $themeId = null, ?int $storeId = null): bool
    {
        $id = self::resolve($themeId, $storeId);

        return in_array($id, self::REFINED_LAYOUT_THEMES, true);
    }

    /**
     * @return array{id: string, label: string, description: string, css: string|array<int, string>|null, fonts: ?string}
     */
    public static function resolved(?int $storeId = null): array
    {
        $id = self::resolve(null, $storeId);

        return self::registry()[$id];
    }

    /**
     * @return array<int, string>
     */
    public static function cssAssetPaths(?int $storeId = null): array
    {
        $css = self::resolved($storeId)['css'] ?? null;
        if ($css === null || $css === '') {
            return [];
        }

        return is_array($css) ? $css : [$css];
    }

    public static function fontsUrl(?int $storeId = null): ?string
    {
        return self::resolved($storeId)['fonts'];
    }

    public static function themeColor(?int $storeId = null): string
    {
        return match (self::resolve(null, $storeId)) {
            self::ELEGANT => '#faf8f5',
            self::NOIR => '#13111a',
            default => '#ffffff',
        };
    }
}
