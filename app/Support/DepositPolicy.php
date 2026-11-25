<?php

namespace App\Support;

use App\Models\CrmSetting;

/**
 * Pré-pagamento: regras partilhadas (tiers) + toggles independentes Agenda / Booking.
 * Scope: organização com override por loja (CrmSetting).
 */
final class DepositPolicy
{
    public const MODE_PERCENT = 'percent';

    public const MODE_FIXED = 'fixed';

    public const CHANNEL_AGENDA = 'agenda';

    public const CHANNEL_BOOKING = 'booking';

    /**
     * @return array{
     *     agenda_enabled: bool,
     *     booking_enabled: bool,
     *     tiers: list<array{up_to: float|null, mode: string, value: float}>
     * }
     */
    public static function forStore(?int $storeId = null): array
    {
        $raw = CrmSetting::getString(CrmSetting::KEY_BOOKING_DEPOSIT_POLICY, '', $storeId);
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return self::normalize($decoded);
            }
        }

        return self::fallbackFromConfig($storeId);
    }

    /**
     * @param  array<string, mixed>  $policy
     */
    public static function setForStore(array $policy, ?int $storeId = null): void
    {
        $normalized = self::normalize($policy);
        CrmSetting::setString(
            CrmSetting::KEY_BOOKING_DEPOSIT_POLICY,
            json_encode($normalized, JSON_UNESCAPED_UNICODE),
            $storeId,
        );
        // Chave legada = toggle Booking.
        CrmSetting::setBool(
            CrmSetting::KEY_BOOKING_ONLINE_PAYMENT_REQUIRED,
            $normalized['booking_enabled'],
            $storeId,
        );
    }

    public static function isAgendaEnabled(?int $storeId = null): bool
    {
        return self::forStore($storeId)['agenda_enabled'] === true;
    }

    public static function isBookingEnabled(?int $storeId = null): bool
    {
        return self::forStore($storeId)['booking_enabled'] === true;
    }

    /** @deprecated Use isBookingEnabled() / isAgendaEnabled() */
    public static function isEnabled(?int $storeId = null): bool
    {
        return self::isBookingEnabled($storeId);
    }

    public static function amountForSubtotal(
        float $subtotal,
        ?int $storeId = null,
        string $channel = self::CHANNEL_BOOKING,
    ): float {
        $policy = self::forStore($storeId);
        if (! self::channelActive($policy, $channel) || $subtotal <= 0.00001) {
            return 0.0;
        }

        $tier = self::matchTier($policy['tiers'], $subtotal);
        if ($tier === null) {
            return 0.0;
        }

        return self::amountFromTier($tier, $subtotal);
    }

    /**
     * @return array{
     *     enabled: bool,
     *     agenda_enabled: bool,
     *     booking_enabled: bool,
     *     amount: float,
     *     mode: string|null,
     *     value: float|null,
     *     percent_equivalent: int
     * }
     */
    public static function resolve(
        ?int $storeId,
        float $subtotal,
        string $channel = self::CHANNEL_BOOKING,
    ): array {
        $policy = self::forStore($storeId);
        $channelOn = self::channelActive($policy, $channel);

        if (! $channelOn || $subtotal <= 0.00001) {
            return [
                'enabled' => false,
                'agenda_enabled' => $policy['agenda_enabled'],
                'booking_enabled' => $policy['booking_enabled'],
                'amount' => 0.0,
                'mode' => null,
                'value' => null,
                'percent_equivalent' => 0,
            ];
        }

        $tier = self::matchTier($policy['tiers'], $subtotal);
        if ($tier === null) {
            return [
                'enabled' => true,
                'agenda_enabled' => $policy['agenda_enabled'],
                'booking_enabled' => $policy['booking_enabled'],
                'amount' => 0.0,
                'mode' => null,
                'value' => null,
                'percent_equivalent' => 0,
            ];
        }

        $amount = self::amountFromTier($tier, $subtotal);
        $percentEq = $tier['mode'] === self::MODE_PERCENT
            ? (int) round($tier['value'])
            : ($subtotal > 0.00001 ? (int) round(($amount / $subtotal) * 100) : 0);

        return [
            'enabled' => true,
            'agenda_enabled' => $policy['agenda_enabled'],
            'booking_enabled' => $policy['booking_enabled'],
            'amount' => $amount,
            'mode' => $tier['mode'],
            'value' => $tier['value'],
            'percent_equivalent' => max(0, min(100, $percentEq)),
        ];
    }

    /**
     * Normaliza input do formulário Definições / Lojas.
     *
     * @param  array<string, mixed>  $input
     * @return array{
     *     agenda_enabled: bool,
     *     booking_enabled: bool,
     *     tiers: list<array{up_to: float|null, mode: string, value: float}>
     * }
     */
    public static function normalize(array $input): array
    {
        $hasSplit = array_key_exists('agenda_enabled', $input)
            || array_key_exists('booking_enabled', $input);

        if ($hasSplit) {
            $agendaEnabled = self::truthy($input['agenda_enabled'] ?? false);
            $bookingEnabled = self::truthy($input['booking_enabled'] ?? false);
        } elseif (array_key_exists('enabled', $input)) {
            // Legado: um único toggle → aplica aos dois canais.
            $both = self::truthy($input['enabled']);
            $agendaEnabled = $both;
            $bookingEnabled = $both;
        } else {
            $agendaEnabled = true;
            $bookingEnabled = true;
        }

        $tiersIn = $input['tiers'] ?? [];
        if (! is_array($tiersIn)) {
            $tiersIn = [];
        }

        $tiers = [];
        foreach ($tiersIn as $row) {
            if (! is_array($row)) {
                continue;
            }
            $mode = (string) ($row['mode'] ?? self::MODE_PERCENT);
            if (! in_array($mode, [self::MODE_PERCENT, self::MODE_FIXED], true)) {
                $mode = self::MODE_PERCENT;
            }

            $rawUpTo = $row['up_to'] ?? null;
            $upTo = null;
            if ($rawUpTo !== null && $rawUpTo !== '' && is_numeric($rawUpTo)) {
                $upTo = round(max(0.0, (float) $rawUpTo), 2);
            }

            $value = is_numeric($row['value'] ?? null) ? (float) $row['value'] : 0.0;
            if ($mode === self::MODE_PERCENT) {
                $value = max(0.0, min(100.0, $value));
            } else {
                $value = max(0.0, round($value, 2));
            }

            $tiers[] = [
                'up_to' => $upTo,
                'mode' => $mode,
                'value' => $value,
            ];
        }

        if ($tiers === []) {
            $tiers = self::fallbackFromConfig()['tiers'];
        }

        usort($tiers, static function (array $a, array $b): int {
            if ($a['up_to'] === null && $b['up_to'] === null) {
                return 0;
            }
            if ($a['up_to'] === null) {
                return 1;
            }
            if ($b['up_to'] === null) {
                return -1;
            }

            return $a['up_to'] <=> $b['up_to'];
        });

        $hasCatchAll = false;
        foreach ($tiers as $t) {
            if ($t['up_to'] === null) {
                $hasCatchAll = true;
                break;
            }
        }
        if (! $hasCatchAll) {
            $last = $tiers[array_key_last($tiers)];
            $tiers[] = [
                'up_to' => null,
                'mode' => $last['mode'],
                'value' => $last['value'],
            ];
        }

        return [
            'agenda_enabled' => $agendaEnabled,
            'booking_enabled' => $bookingEnabled,
            'tiers' => array_values($tiers),
        ];
    }

    /**
     * Sem política gravada: % do .env e estado legado booking.online_payment_required.
     *
     * @return array{
     *     agenda_enabled: bool,
     *     booking_enabled: bool,
     *     tiers: list<array{up_to: float|null, mode: string, value: float}>
     * }
     */
    private static function fallbackFromConfig(?int $storeId = null): array
    {
        $percent = max(0, min(100, (int) config('booking.deposit_percent', 20)));
        $legacyOnline = CrmSetting::getBool(CrmSetting::KEY_BOOKING_ONLINE_PAYMENT_REQUIRED, true, $storeId);

        return [
            // Toggles independentes do valor das regras (pode ter 0% com canal ligado).
            'agenda_enabled' => $legacyOnline,
            'booking_enabled' => $legacyOnline,
            'tiers' => [
                [
                    'up_to' => null,
                    'mode' => self::MODE_PERCENT,
                    'value' => (float) $percent,
                ],
            ],
        ];
    }

    /**
     * @param  array{agenda_enabled: bool, booking_enabled: bool, tiers: list}  $policy
     */
    private static function channelActive(array $policy, string $channel): bool
    {
        return match ($channel) {
            self::CHANNEL_AGENDA => $policy['agenda_enabled'] === true,
            self::CHANNEL_BOOKING => $policy['booking_enabled'] === true,
            default => false,
        };
    }

    private static function truthy(mixed $value): bool
    {
        return $value === true
            || $value === 1
            || $value === '1'
            || $value === 'on';
    }

    /**
     * @param  list<array{up_to: float|null, mode: string, value: float}>  $tiers
     * @return array{up_to: float|null, mode: string, value: float}|null
     */
    private static function matchTier(array $tiers, float $subtotal): ?array
    {
        foreach ($tiers as $tier) {
            if ($tier['up_to'] === null) {
                return $tier;
            }
            if ($subtotal <= ((float) $tier['up_to']) + 0.00001) {
                return $tier;
            }
        }

        return $tiers !== [] ? $tiers[array_key_last($tiers)] : null;
    }

    /**
     * @param  array{up_to: float|null, mode: string, value: float}  $tier
     */
    private static function amountFromTier(array $tier, float $subtotal): float
    {
        if ($tier['mode'] === self::MODE_FIXED) {
            return round(min(max(0.0, (float) $tier['value']), $subtotal), 2);
        }

        $pct = max(0.0, min(100.0, (float) $tier['value']));

        return round($subtotal * ($pct / 100), 2);
    }
}
