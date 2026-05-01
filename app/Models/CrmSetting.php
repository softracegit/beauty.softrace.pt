<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmSetting extends Model
{
    public const KEY_BOOKING_ONLINE_PAYMENT_REQUIRED = 'booking.online_payment_required';

    public const KEY_BOOKING_SLOT_HOLD_MINUTES = 'booking.slot_hold_minutes';

    public const KEY_BOOKING_ANY_STAFF_RULE = 'booking.any_staff_rule';

    public const BOOKING_ANY_STAFF_RULE_A = 'day_load_then_agenda_order';

    public const BOOKING_ANY_STAFF_RULE_B = 'agenda_order_then_day_load';

    public const BOOKING_ANY_STAFF_RULE_C = 'month_load_then_agenda_order';

    public const BOOKING_ANY_STAFF_RULE_D = 'agenda_order_then_month_load';

    protected $fillable = [
        'key',
        'value',
    ];

    public static function getBool(string $key, bool $default = false): bool
    {
        $raw = static::query()->where('key', $key)->value('value');
        if ($raw === null || $raw === '') {
            return $default;
        }
        $s = strtolower(trim((string) $raw));

        return in_array($s, ['1', 'true', 'yes', 'on'], true);
    }

    public static function setBool(string $key, bool $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value ? '1' : '0'],
        );
    }

    public static function onlineBookingPaymentRequired(): bool
    {
        return self::getBool(self::KEY_BOOKING_ONLINE_PAYMENT_REQUIRED, true);
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $raw = static::query()->where('key', $key)->value('value');
        if ($raw === null || trim((string) $raw) === '') {
            return $default;
        }
        if (! is_numeric($raw)) {
            return $default;
        }

        return (int) $raw;
    }

    public static function setInt(string $key, int $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value],
        );
    }

    public static function getString(string $key, string $default = ''): string
    {
        $raw = static::query()->where('key', $key)->value('value');
        if ($raw === null) {
            return $default;
        }

        $value = trim((string) $raw);

        return $value !== '' ? $value : $default;
    }

    public static function setString(string $key, string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => trim($value)],
        );
    }

    public static function bookingSlotHoldMinutes(): int
    {
        return max(1, self::getInt(self::KEY_BOOKING_SLOT_HOLD_MINUTES, 6));
    }

    /**
     * Textos para o ecrã de definições (título + descrição).
     *
     * @return array<string, array{title: string, description: string}>
     */
    public static function bookingAnyStaffRulesUi(): array
    {
        return [
            self::BOOKING_ANY_STAFF_RULE_A => [
                'title' => 'Equilibrar o dia entre colaboradores.',
                'description' => 'Distribui as marcações por quem tem menos clientes hoje.',
            ],
            self::BOOKING_ANY_STAFF_RULE_B => [
                'title' => 'Atender o cliente o mais cedo possível',
                'description' => 'Escolhe sempre o horário disponível mais próximo.',
            ],
            self::BOOKING_ANY_STAFF_RULE_C => [
                'title' => 'Equilibrar o trabalho ao longo do mês',
                'description' => 'Dá prioridade a quem teve menos clientes este mês.',
            ],
            self::BOOKING_ANY_STAFF_RULE_D => [
                'title' => 'Atender cedo, mantendo equilíbrio mensal',
                'description' => 'Escolhe o horário mais cedo e, em caso de empate, equilibra entre colaboradores.',
            ],
        ];
    }

    /**
     * @return array<string, string> mapa id da regra → título (uma linha)
     */
    public static function bookingAnyStaffRules(): array
    {
        $ui = self::bookingAnyStaffRulesUi();

        return array_map(fn (array $row): string => $row['title'], $ui);
    }

    public static function bookingAnyStaffRule(): string
    {
        $rules = self::bookingAnyStaffRulesUi();
        $default = self::BOOKING_ANY_STAFF_RULE_A;
        $value = self::getString(self::KEY_BOOKING_ANY_STAFF_RULE, $default);

        return array_key_exists($value, $rules) ? $value : $default;
    }
}
