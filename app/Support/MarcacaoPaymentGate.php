<?php

namespace App\Support;

use App\Models\CalendarEvent;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Liquidação final / estado «Pago» só no dia civil da marcação (fuso da loja).
 * Pré-pagamento / sinal de booking não passa por aqui.
 */
final class MarcacaoPaymentGate
{
    public static function canMarkPaid(CalendarEvent $event): bool
    {
        if (($event->event_type ?? '') !== CalendarEvent::TYPE_MARCACAO) {
            return false;
        }

        if ($event->start_at === null || $event->store_id === null) {
            return false;
        }

        $storeId = (int) $event->store_id;
        $tz = StoreBusinessTime::timezoneForStore($storeId);
        $nowLocal = StoreBusinessTime::nowForStore($storeId);
        $appointmentDayStart = Carbon::instance($event->start_at)->timezone($tz)->startOfDay();

        return $nowLocal->greaterThanOrEqualTo($appointmentDayStart);
    }

    public static function denialMessage(): string
    {
        return 'Só é possível marcar como paga no dia da marcação.';
    }

    /**
     * @throws ValidationException
     */
    public static function assertCanMarkPaid(CalendarEvent $event): void
    {
        if (self::canMarkPaid($event)) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => self::denialMessage(),
        ]);
    }

    public static function jsonDenied(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => self::denialMessage(),
            'message' => self::denialMessage(),
        ], 422);
    }
}
