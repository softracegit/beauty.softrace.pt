<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmSetting extends Model
{
    public const KEY_BOOKING_ONLINE_PAYMENT_REQUIRED = 'booking.online_payment_required';

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
}
