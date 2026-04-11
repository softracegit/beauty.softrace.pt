<?php

namespace App\Models;

use App\Mail\BookingMagicLinkMail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BookingMagicLoginToken extends Model
{
    protected $table = 'booking_magic_login_tokens';

    protected $fillable = [
        'user_id',
        'token',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Revoga tokens anteriores e envia um novo link mágico por email.
     */
    public static function sendFreshLink(User $user): void
    {
        if (! $user->isBookingClient()) {
            return;
        }

        static::query()->where('user_id', $user->id)->delete();

        $plain = bin2hex(random_bytes(32));
        $ttl = max(5, (int) config('booking.magic_link_ttl_minutes', 60));

        static::create([
            'user_id' => $user->id,
            'token' => $plain,
            'expires_at' => now()->addMinutes($ttl),
        ]);

        $url = route('booking.login.magic', ['token' => $plain], absolute: true);

        try {
            Mail::to($user->email)->send(new BookingMagicLinkMail($user, $url));
        } catch (\Throwable $e) {
            Log::error('Envio do email de magic link (booking) falhou.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            // Não relançar: marcação e conta já existem; o cliente pode pedir novo link em /booking/acesso.
        }
    }
}
