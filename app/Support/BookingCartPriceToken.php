<?php

namespace App\Support;

use App\Models\CrmSetting;

/**
 * HMAC tokens that freeze catalog prices from the moment they appear in the booking UI
 * until checkout (cart TTL). Prevents clients from forging arbitrary prices.
 */
final class BookingCartPriceToken
{
    public const KIND_SERVICE = 'service';

    public const KIND_OPTION = 'option';

    public const KIND_EXTRA = 'extra';

    public const KIND_FEE = 'fee';

    /**
     * @return list<string>
     */
    public static function kinds(): array
    {
        return [
            self::KIND_SERVICE,
            self::KIND_OPTION,
            self::KIND_EXTRA,
            self::KIND_FEE,
        ];
    }

    public static function ttlSeconds(?int $storeId = null): int
    {
        try {
            $hours = CrmSetting::bookingCartTtlHours($storeId);
        } catch (\Throwable) {
            $hours = CrmSetting::BOOKING_CART_TTL_HOURS_DEFAULT;
        }

        return max(3600, $hours * 3600);
    }

    public static function issue(string $kind, int $id, float $price, ?int $issuedAt = null, ?int $storeId = null): string
    {
        $kind = strtolower(trim($kind));
        if (! in_array($kind, self::kinds(), true) || $id <= 0) {
            return '';
        }

        $payload = [
            'k' => $kind,
            'i' => $id,
            'p' => round($price, 2),
            't' => $issuedAt ?? time(),
        ];
        if ($storeId !== null && $storeId > 0) {
            $payload['s'] = $storeId;
        }

        $body = self::b64(json_encode($payload, JSON_THROW_ON_ERROR));

        return $body.'.'.self::b64(hash_hmac('sha256', $body, self::secret(), true));
    }

    /**
     * @return array{kind: string, id: int, price: float, issued_at: int, store_id: ?int}|null
     */
    public static function parse(string $token): ?array
    {
        $token = trim($token);
        if ($token === '' || ! str_contains($token, '.')) {
            return null;
        }

        [$body, $sig] = explode('.', $token, 2);
        if ($body === '' || $sig === '') {
            return null;
        }

        $expected = self::b64(hash_hmac('sha256', $body, self::secret(), true));
        if (! hash_equals($expected, $sig)) {
            return null;
        }

        try {
            $json = self::ub64($body);
            if ($json === null) {
                return null;
            }
            /** @var array<string, mixed> $payload */
            $payload = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        $kind = strtolower(trim((string) ($payload['k'] ?? '')));
        $id = (int) ($payload['i'] ?? 0);
        $price = round((float) ($payload['p'] ?? -1), 2);
        $issuedAt = (int) ($payload['t'] ?? 0);
        $storeId = isset($payload['s']) ? (int) $payload['s'] : null;

        if (! in_array($kind, self::kinds(), true) || $id <= 0 || $price < 0 || $issuedAt <= 0) {
            return null;
        }

        return [
            'kind' => $kind,
            'id' => $id,
            'price' => $price,
            'issued_at' => $issuedAt,
            'store_id' => $storeId !== null && $storeId > 0 ? $storeId : null,
        ];
    }

    /**
     * @return array{kind: string, id: int, price: float, issued_at: int, store_id: ?int}|null
     */
    public static function verify(string $token, string $kind, int $id, ?int $storeId = null): ?array
    {
        $parsed = self::parse($token);
        if ($parsed === null) {
            return null;
        }

        if ($parsed['kind'] !== strtolower(trim($kind)) || $parsed['id'] !== $id) {
            return null;
        }

        if ($storeId !== null && $storeId > 0 && $parsed['store_id'] !== null && $parsed['store_id'] !== $storeId) {
            return null;
        }

        $ttl = self::ttlSeconds($storeId ?? $parsed['store_id']);
        if ($parsed['issued_at'] + $ttl < time()) {
            return null;
        }

        return $parsed;
    }

    private static function secret(): string
    {
        return (string) config('app.key');
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function ub64(string $encoded): ?string
    {
        $padded = strtr($encoded, '-_', '+/');
        $pad = strlen($padded) % 4;
        if ($pad > 0) {
            $padded .= str_repeat('=', 4 - $pad);
        }
        $raw = base64_decode($padded, true);

        return $raw === false ? null : $raw;
    }
}
