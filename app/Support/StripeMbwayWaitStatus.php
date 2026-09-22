<?php

namespace App\Support;

/**
 * Interpreta o estado de um PaymentIntent MB Way durante a espera na agenda.
 */
final class StripeMbwayWaitStatus
{
    public const OUTCOME_SUCCEEDED = 'succeeded';

    public const OUTCOME_WAITING = 'waiting';

    public const OUTCOME_TERMINAL = 'terminal';

    /**
     * @return array{outcome: string, status: string, message: string}
     */
    public static function fromIntent(object $intent): array
    {
        $status = (string) ($intent->status ?? 'unknown');

        if ($status === 'succeeded') {
            return [
                'outcome' => self::OUTCOME_SUCCEEDED,
                'status' => $status,
                'message' => 'Pagamento MB Way confirmado.',
            ];
        }

        // Pedido anulado ou cliente recusou / falhou (confirm:true → volta a requires_payment_method).
        if ($status === 'canceled' || $status === 'requires_payment_method') {
            return [
                'outcome' => self::OUTCOME_TERMINAL,
                'status' => $status,
                'message' => self::failureMessage($intent)
                    ?? 'O cliente recusou ou o pagamento MB Way foi cancelado.',
            ];
        }

        return [
            'outcome' => self::OUTCOME_WAITING,
            'status' => $status !== '' ? $status : 'unknown',
            'message' => 'Pagamento MB Way ainda não confirmado.',
        ];
    }

    private static function failureMessage(object $intent): ?string
    {
        $lastErr = $intent->last_payment_error ?? null;
        if (! is_object($lastErr)) {
            return null;
        }

        $code = isset($lastErr->code) && is_string($lastErr->code) ? $lastErr->code : '';
        $decline = isset($lastErr->decline_code) && is_string($lastErr->decline_code)
            ? $lastErr->decline_code
            : '';

        if ($code === 'payment_intent_payment_attempt_failed'
            || $decline === 'generic_decline'
            || $code === 'card_declined') {
            return 'O cliente recusou o pagamento MB Way.';
        }

        $msg = isset($lastErr->message) && is_string($lastErr->message)
            ? trim($lastErr->message)
            : '';

        if ($msg !== '') {
            return 'Pagamento MB Way não concluído: '.$msg;
        }

        return null;
    }
}
