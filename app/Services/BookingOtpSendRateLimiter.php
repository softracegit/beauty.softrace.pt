<?php

namespace App\Services;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;

final class BookingOtpSendRateLimiter
{
    /**
     * @param  string  $bucket  Identificador opaco (ex.: prefixo + loja + hash do alvo)
     * @param  'login'|'verify'  $errorKey  Chave usada no JSON de erros (422)
     */
    public function assertCanSend(string $bucket, string $errorKey = 'login'): void
    {
        $cooldown = max(0, (int) config('booking.otp_send_cooldown_seconds', 30));
        $maxPerWindow = (int) config('booking.otp_send_max_per_window', 6);
        $windowHours = max(1, (int) config('booking.otp_send_count_window_hours', 1));
        $lockoutHours = max(1, (int) config('booking.otp_send_lockout_hours', 2));

        $lockKey = $this->lockKey($bucket);
        if (Cache::has($lockKey)) {
            $this->throwLockout($errorKey, $lockoutHours);
        }

        if ($cooldown > 0) {
            $last = Cache::get($this->lastSendKey($bucket));
            if (is_int($last) || is_numeric($last)) {
                $elapsed = time() - (int) $last;
                if ($elapsed < $cooldown) {
                    $this->throwCooldown($errorKey, (int) $cooldown - $elapsed);
                }
            }
        }

        if ($maxPerWindow > 0) {
            $hits = $this->normalizedHits($bucket, $windowHours);
            if (count($hits) >= $maxPerWindow) {
                Cache::put($lockKey, 1, now()->addHours($lockoutHours));
                $this->throwLockout($errorKey, $lockoutHours);
            }
        }
    }

    /**
     * @param  string  $bucket  Mesmo valor usado em assertCanSend
     */
    public function recordSuccessfulSend(string $bucket): void
    {
        $cooldown = max(0, (int) config('booking.otp_send_cooldown_seconds', 30));
        $windowHours = max(1, (int) config('booking.otp_send_count_window_hours', 1));
        $maxPerWindow = (int) config('booking.otp_send_max_per_window', 6);

        $now = time();
        if ($cooldown > 0) {
            Cache::put($this->lastSendKey($bucket), $now, now()->addHours(24));
        }

        if ($maxPerWindow > 0) {
            $hitsKey = $this->hitsKey($bucket);
            $hits = $this->normalizedHits($bucket, $windowHours);
            $hits[] = $now;
            Cache::put($hitsKey, $hits, now()->addHours($windowHours));
        }
    }

    /**
     * @return list<int>
     */
    private function normalizedHits(string $bucket, int $windowHours): array
    {
        $windowSec = $windowHours * 3600;
        $cutoff = time() - $windowSec;
        $raw = Cache::get($this->hitsKey($bucket), []);
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $t) {
            if (is_int($t) || is_numeric($t)) {
                $ti = (int) $t;
                if ($ti > $cutoff) {
                    $out[] = $ti;
                }
            }
        }

        return array_values($out);
    }

    private function lockKey(string $bucket): string
    {
        return 'booking_otp_lock:'.$bucket;
    }

    private function lastSendKey(string $bucket): string
    {
        return 'booking_otp_last:'.$bucket;
    }

    private function hitsKey(string $bucket): string
    {
        return 'booking_otp_hits:'.$bucket;
    }

    private function throwCooldown(string $errorKey, int $retryAfterSeconds): never
    {
        $retryAfterSeconds = max(1, $retryAfterSeconds);
        $msg = sprintf(
            'Aguarde %d segundos antes de pedir um novo código.',
            $retryAfterSeconds
        );

        throw new HttpResponseException(response()->json([
            'message' => $msg,
            'errors' => [$errorKey => [$msg]],
            'retry_after_seconds' => $retryAfterSeconds,
        ], 422));
    }

    private function throwLockout(string $errorKey, int $lockoutHours): never
    {
        $msg = $lockoutHours === 1
            ? 'Foram feitos demasiados pedidos de código. Tente novamente daqui a 1 hora.'
            : sprintf(
                'Foram feitos demasiados pedidos de código. Tente novamente daqui a %d horas.',
                $lockoutHours
            );

        throw new HttpResponseException(response()->json([
            'message' => $msg,
            'errors' => [$errorKey => [$msg]],
        ], 422));
    }
}
