<?php

namespace App\Support;

use App\Models\CrmSetting;
use App\Models\Sale;

/**
 * Catálogo de métodos de pagamento por loja (Definições → Pagamentos).
 *
 * Canais de UI: Agenda (caixa + pré-pagamento) e Booking (site público).
 * Códigos estáveis em {@see Sale} para histórico / Vendus / relatórios.
 */
class PaymentMethodCatalog
{
    /** @deprecated Use CHANNEL_AGENDA — mantido para callers da caixa. */
    public const CHANNEL_CAIXA = 'caixa';

    public const CHANNEL_BOOKING = 'booking';

    /** @deprecated Use CHANNEL_AGENDA — mantido para callers de pré-pagamento. */
    public const CHANNEL_RESERVA = 'reserva';

    public const CHANNEL_AGENDA = 'agenda';

    public const PROVIDER_MANUAL = 'manual';

    public const PROVIDER_STRIPE = 'stripe';

    public const PROVIDER_WALLET = 'wallet';

    /**
     * Definições fixas do produto (não editáveis como código livre).
     *
     * @return array<string, array{code: string, label: string, provider: string, icon: string, description: string}>
     */
    public static function definitions(): array
    {
        return [
            Sale::PAYMENT_DINHEIRO => [
                'code' => Sale::PAYMENT_DINHEIRO,
                'label' => 'Dinheiro',
                'provider' => self::PROVIDER_MANUAL,
                'icon' => 'ph-money',
                'description' => 'Numerário na caixa (com troco).',
            ],
            Sale::PAYMENT_CARTAO => [
                'code' => Sale::PAYMENT_CARTAO,
                'label' => 'Cartão',
                'provider' => self::PROVIDER_STRIPE,
                'icon' => 'ph-credit-card',
                'description' => 'Cartão guardado via Stripe (agenda e marcações online).',
            ],
            Sale::PAYMENT_MBWAY => [
                'code' => Sale::PAYMENT_MBWAY,
                'label' => 'MBWay',
                'provider' => self::PROVIDER_STRIPE,
                'icon' => 'ph-device-mobile',
                'description' => 'Cobrança MB WAY automática via Stripe.',
            ],
            Sale::PAYMENT_MBWAY_MANUAL => [
                'code' => Sale::PAYMENT_MBWAY_MANUAL,
                'label' => 'MBWay (manual)',
                'provider' => self::PROVIDER_MANUAL,
                'icon' => 'ph-device-mobile',
                'description' => 'Registo interno quando o cliente já pagou MB Way fora do Stripe.',
            ],
            Sale::PAYMENT_TRANSFERENCIA => [
                'code' => Sale::PAYMENT_TRANSFERENCIA,
                'label' => 'Transferência',
                'provider' => self::PROVIDER_MANUAL,
                'icon' => 'ph-bank',
                'description' => 'Transferência bancária registada manualmente.',
            ],
            Sale::PAYMENT_MULTIBANCO => [
                'code' => Sale::PAYMENT_MULTIBANCO,
                'label' => 'Multibanco',
                'provider' => self::PROVIDER_STRIPE,
                'icon' => 'ph-barcode',
                'description' => 'Referência Multibanco via Stripe (marcações online).',
            ],
            Sale::PAYMENT_CREDITOS_CARTEIRA => [
                'code' => Sale::PAYMENT_CREDITOS_CARTEIRA,
                'label' => 'Créditos (carteira)',
                'provider' => self::PROVIDER_WALLET,
                'icon' => 'ph-wallet',
                'description' => 'Débito do saldo de créditos do cliente.',
            ],
        ];
    }

    /**
     * Estado por loja: canais activos + ordem.
     *
     * @return list<array{code: string, label: string, provider: string, icon: string, description: string, sort: int, agenda: bool, booking: bool}>
     */
    public static function forStore(?int $storeId = null): array
    {
        $defs = self::definitions();
        $saved = CrmSetting::paymentMethodsConfig($storeId);
        $byCode = [];
        foreach ($saved as $row) {
            $code = (string) ($row['code'] ?? '');
            if ($code === '' || ! isset($defs[$code])) {
                continue;
            }
            $byCode[$code] = $row;
        }

        $defaults = self::defaultChannelFlags($storeId);
        $out = [];
        $sort = 0;
        foreach ($defs as $code => $def) {
            $row = $byCode[$code] ?? null;
            $flags = $defaults[$code] ?? ['agenda' => false, 'booking' => false, 'sort' => $sort];
            $out[] = [
                'code' => $code,
                'label' => $def['label'],
                'provider' => $def['provider'],
                'icon' => $def['icon'],
                'description' => $def['description'],
                'sort' => is_array($row) && isset($row['sort']) ? (int) $row['sort'] : (int) $flags['sort'],
                'agenda' => is_array($row) ? self::resolveAgendaFlag($row, $flags) : (bool) $flags['agenda'],
                'booking' => is_array($row) ? self::resolveBookingFlag($row, $flags) : (bool) $flags['booking'],
            ];
            $sort += 10;
        }

        usort($out, fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

        return $out;
    }

    /**
     * Métodos activos num canal.
     * `caixa` / `reserva` leem o flag unificado `agenda`.
     *
     * @return list<array{code: string, label: string, provider: string, icon: string, description: string, sort: int, agenda: bool, booking: bool}>
     */
    public static function enabledForChannel(string $channel, ?int $storeId = null): array
    {
        $flag = match ($channel) {
            self::CHANNEL_AGENDA, self::CHANNEL_CAIXA, self::CHANNEL_RESERVA => self::CHANNEL_AGENDA,
            self::CHANNEL_BOOKING => self::CHANNEL_BOOKING,
            default => self::CHANNEL_AGENDA,
        };

        $stripeReady = StripeCredentials::isReady($storeId);

        return array_values(array_filter(
            self::forStore($storeId),
            function (array $row) use ($flag, $stripeReady): bool {
                if (! ($row[$flag] ?? false)) {
                    return false;
                }
                if ($row['provider'] === self::PROVIDER_STRIPE && ! $stripeReady) {
                    return false;
                }

                return true;
            },
        ));
    }

    public static function isEnabled(string $code, string $channel, ?int $storeId = null): bool
    {
        foreach (self::enabledForChannel($channel, $storeId) as $row) {
            if ($row['code'] === $code) {
                return true;
            }
        }

        return false;
    }

    public static function providerFor(string $code, ?int $storeId = null): ?string
    {
        foreach (self::forStore($storeId) as $row) {
            if ($row['code'] === $code) {
                return $row['provider'];
            }
        }

        return self::definitions()[$code]['provider'] ?? null;
    }

    public static function isStripeMethod(string $code, ?int $storeId = null): bool
    {
        return self::providerFor($code, $storeId) === self::PROVIDER_STRIPE;
    }

    /**
     * Mapa code → label (histórico + UI).
     *
     * @return array<string, string>
     */
    public static function labels(?int $storeId = null): array
    {
        $labels = [];
        foreach (self::forStore($storeId) as $row) {
            $labels[$row['code']] = $row['label'];
        }

        // Histórico / importações — já não é configurável na UI.
        $labels[Sale::PAYMENT_OUTRO] = 'Outro';

        return $labels;
    }

    /**
     * Persistência a partir do formulário de definições.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function saveFromRequest(array $rows, ?int $storeId = null): void
    {
        $defs = self::definitions();
        $normalized = [];
        $sort = 0;
        foreach ($rows as $row) {
            $code = (string) ($row['code'] ?? '');
            if ($code === '' || ! isset($defs[$code])) {
                continue;
            }
            $agenda = (bool) ($row['agenda'] ?? false);
            $normalized[] = [
                'code' => $code,
                'sort' => isset($row['sort']) ? (int) $row['sort'] : $sort,
                'agenda' => $agenda,
                'booking' => (bool) ($row['booking'] ?? false),
            ];
            $sort += 10;
        }

        $present = array_column($normalized, 'code');
        foreach (array_keys($defs) as $code) {
            if (in_array($code, $present, true)) {
                continue;
            }
            $normalized[] = [
                'code' => $code,
                'sort' => $sort,
                'agenda' => false,
                'booking' => false,
            ];
            $sort += 10;
        }

        usort($normalized, fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);
        CrmSetting::setPaymentMethodsConfig($normalized, $storeId);
    }

    /**
     * Defaults alinhados com o comportamento histórico (antes da config na UI).
     *
     * @return array<string, array{agenda: bool, booking: bool, sort: int}>
     */
    public static function defaultChannelFlags(?int $storeId = null): array
    {
        $online = CrmSetting::onlineBookingPaymentRequired($storeId);

        return [
            Sale::PAYMENT_DINHEIRO => ['agenda' => true, 'booking' => false, 'sort' => 10],
            Sale::PAYMENT_CARTAO => ['agenda' => true, 'booking' => true, 'sort' => 20],
            Sale::PAYMENT_MBWAY => [
                'agenda' => $online,
                'booking' => $online,
                'sort' => 30,
            ],
            Sale::PAYMENT_MBWAY_MANUAL => [
                'agenda' => ! $online,
                'booking' => false,
                'sort' => 40,
            ],
            Sale::PAYMENT_TRANSFERENCIA => [
                'agenda' => ! $online,
                'booking' => false,
                'sort' => 50,
            ],
            Sale::PAYMENT_MULTIBANCO => [
                'agenda' => false,
                'booking' => $online,
                'sort' => 60,
            ],
            Sale::PAYMENT_CREDITOS_CARTEIRA => ['agenda' => true, 'booking' => false, 'sort' => 70],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{agenda: bool, booking: bool, sort: int}  $flags
     */
    private static function resolveAgendaFlag(array $row, array $flags): bool
    {
        if (array_key_exists('agenda', $row)) {
            return (bool) $row['agenda'];
        }

        // Compat: configs antigas com caixa / reserva separados.
        if (array_key_exists('caixa', $row) || array_key_exists('reserva', $row)) {
            return (bool) ($row['caixa'] ?? false) || (bool) ($row['reserva'] ?? false);
        }

        return (bool) $flags['agenda'];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{agenda: bool, booking: bool, sort: int}  $flags
     */
    private static function resolveBookingFlag(array $row, array $flags): bool
    {
        if (array_key_exists('booking', $row)) {
            return (bool) $row['booking'];
        }

        return (bool) $flags['booking'];
    }
}
