<?php

namespace App\Support;

final class FriendlyErrorMessages
{
    public const CSRF_SESSION_EXPIRED = 'A sua sessão expirou ou a página ficou aberta demasiado tempo. Atualize a página e tente novamente.';

    public const SLOT_HOLD_RESTART = 'Ocorreu um erro, por favor pressione «Voltar ao início».';

    public static function isCsrfMismatchMessage(?string $message): bool
    {
        if ($message === null || trim($message) === '') {
            return false;
        }

        $normalized = strtolower($message);

        return str_contains($normalized, 'csrf')
            || str_contains($normalized, 'token mismatch')
            || str_contains($normalized, 'page expired')
            || str_contains($normalized, 'sua sessão expirou');
    }

    public static function forHttpStatus(int $status, ?string $message = null, ?string $fallback = null): string
    {
        if ($status === 419 || self::isCsrfMismatchMessage($message)) {
            return self::CSRF_SESSION_EXPIRED;
        }

        if ($message !== null && trim($message) !== '') {
            return trim($message);
        }

        return $fallback ?? 'Não foi possível processar o pedido. Tente novamente.';
    }
}
