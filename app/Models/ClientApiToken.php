<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Token da app móvel (cliente de marcação online).
 * Formato entregue à app: "{id}|{segredo}". Na BD fica só o SHA-256 do segredo.
 */
class ClientApiToken extends Model
{
    /** Validade de cada sessão na app (renova-se ao usar). */
    public const LIFETIME_DAYS = 90;

    protected $fillable = ['user_id', 'store_id', 'name', 'token_hash', 'last_used_at', 'expires_at'];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array{token: ClientApiToken, plain: string}
     */
    public static function issue(User $user, ?int $storeId, ?string $deviceName = null): array
    {
        $secret = Str::random(64);
        $token = self::query()->create([
            'user_id' => $user->id,
            'store_id' => $storeId,
            'name' => $deviceName !== null ? Str::limit($deviceName, 120, '') : null,
            'token_hash' => hash('sha256', $secret),
            'expires_at' => now()->addDays(self::LIFETIME_DAYS),
        ]);

        return ['token' => $token, 'plain' => $token->id.'|'.$secret];
    }

    public static function findValid(string $plain): ?self
    {
        if (! str_contains($plain, '|')) {
            return null;
        }
        [$id, $secret] = explode('|', $plain, 2);
        if (! ctype_digit($id) || $secret === '') {
            return null;
        }

        $token = self::query()->with('user')->find((int) $id);
        if (! $token instanceof self || ! hash_equals($token->token_hash, hash('sha256', $secret))) {
            return null;
        }
        if ($token->expires_at !== null && $token->expires_at->isPast()) {
            return null;
        }

        return $token;
    }

    /** Marca uso (no máximo uma escrita a cada 10 minutos) e prolonga a validade. */
    public function touchUsage(): void
    {
        if ($this->last_used_at !== null && $this->last_used_at->gt(now()->subMinutes(10))) {
            return;
        }
        $this->forceFill([
            'last_used_at' => now(),
            'expires_at' => now()->addDays(self::LIFETIME_DAYS),
        ])->saveQuietly();
    }
}
