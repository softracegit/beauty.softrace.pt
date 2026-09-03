<?php

namespace App\Support;

use App\Models\CalendarEvent;

/**
 * Razões fechadas para registar falta ou cancelamento na agenda.
 */
final class MarcacaoTerminalReasons
{
    public const OUTRA = 'outra';

    /** @var list<string> */
    public const FALTOU = [
        'O cliente não forneceu razão',
        'O cliente esqueceu-se',
        'O cliente teve um imprevisto pessoal',
        'Cliente não disponível',
        'Outra razão',
    ];

    /** @var list<string> */
    public const CANCELADO = [
        'Marcação inserida por engano',
        'Marcação inserida para testes',
        'Marcação duplicada',
        'Cliente pediu para cancelar',
        'Outra razão',
    ];

    /**
     * @return list<string>
     */
    public static function forStatus(string $status): array
    {
        return match ($status) {
            CalendarEvent::STATUS_FALTOU => self::FALTOU,
            CalendarEvent::STATUS_CANCELADO => self::CANCELADO,
            default => [],
        };
    }

    public static function isPresetReason(string $status, string $reason): bool
    {
        $reason = trim($reason);

        return $reason !== '' && in_array($reason, self::forStatus($status), true);
    }

    public static function isOutraReason(string $reason): bool
    {
        return trim($reason) === 'Outra razão';
    }

    /**
     * Valida payload de razão/notas para faltou ou cancelado.
     *
     * @return array{reason: string, notes: ?string}|null null = inválido
     */
    public static function resolveStoredReason(
        string $status,
        ?string $cancellationReason,
        ?string $outraText,
        ?string $notes,
    ): ?array {
        $preset = trim((string) ($cancellationReason ?? ''));
        $outra = trim((string) ($outraText ?? ''));
        $notesTrim = trim((string) ($notes ?? ''));

        if ($preset === '') {
            return null;
        }

        if (! self::isPresetReason($status, $preset)) {
            return null;
        }

        $storedReason = $preset;
        if (self::isOutraReason($preset)) {
            if ($outra === '') {
                return null;
            }
            $storedReason = $outra;
        }

        return [
            'reason' => $storedReason,
            'notes' => $notesTrim !== '' ? $notesTrim : null,
        ];
    }
}
