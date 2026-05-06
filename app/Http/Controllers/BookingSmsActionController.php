<?php

namespace App\Http\Controllers;

use App\Models\BookingSmsActionLink;
use App\Models\CalendarEvent;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingSmsActionController extends Controller
{
    public function manage(string $token): View
    {
        $resolved = $this->resolveLink($token);
        if (! $resolved['ok']) {
            return $this->resultView(
                false,
                'Link inválido ou expirado',
                'Este link já não é válido. Peça um novo lembrete.',
                '',
                config('app.name', 'Loja'),
                'error'
            );
        }

        return view('booking.sms-manage', [
            'token' => $token,
            'event' => $resolved['event'],
            'businessName' => (string) ($resolved['event']->store?->name ?? config('app.name', 'Loja')),
            'bookingStoreSlug' => (string) ($resolved['event']->store?->slug ?? \App\Models\Store::defaultPublicBookingStoreSlug()),
        ]);
    }

    public function confirm(string $token): View
    {
        return $this->handleAction($token, CalendarEvent::STATUS_CONFIRMADO);
    }

    public function cancel(Request $request, string $token): View
    {
        $reason = trim((string) $request->input('cancellation_reason', ''));

        return $this->handleAction($token, CalendarEvent::STATUS_CANCELADO, $reason);
    }

    private function handleAction(string $token, string $targetStatus, string $cancellationReason = ''): View
    {
        $resolved = $this->resolveLink($token);
        if (! $resolved['ok']) {
            return $this->resultView(
                false,
                'Link inválido ou expirado',
                'Este link já não é válido. Peça um novo lembrete.',
                '',
                config('app.name', 'Loja'),
                'error'
            );
        }

        /** @var CalendarEvent $calendarEvent */
        $calendarEvent = $resolved['event'];

        $currentStatus = (string) ($calendarEvent->status ?? CalendarEvent::STATUS_AGENDADO);
        $result = [
            'ok' => false,
            'title' => 'Não foi possível atualizar a marcação',
            'message' => 'Tente novamente mais tarde ou contacte a loja.',
        ];

        if (in_array($currentStatus, [CalendarEvent::STATUS_CANCELADO, CalendarEvent::STATUS_FALTOU], true)) {
            $result = [
                'ok' => false,
                'title' => 'Marcação já fechada',
                'message' => 'Esta marcação já está cancelada ou marcada como falta.',
            ];
        } elseif ($targetStatus === CalendarEvent::STATUS_CANCELADO) {
            $finalReason = $cancellationReason !== ''
                ? $cancellationReason
                : 'Cancelado pelo cliente via link SMS.';

            $calendarEvent->forceFill([
                'status' => CalendarEvent::STATUS_CANCELADO,
                'cancellation_type' => CalendarEvent::STATUS_CANCELADO,
                'cancellation_reason' => $finalReason,
                'refund_reserva' => null,
                'avisou_dentro_prazo' => null,
            ])->save();

            $result = [
                'ok' => true,
                'title' => 'Marcação cancelada',
                'message' => 'A sua marcação foi cancelada com sucesso.',
            ];
        } elseif ($currentStatus === CalendarEvent::STATUS_CONFIRMADO) {
            $result = [
                'ok' => true,
                'title' => 'Marcação já confirmada',
                'message' => 'A sua marcação já se encontra confirmada.',
            ];
        } else {
            if (! $calendarEvent->canTransitionTo(CalendarEvent::STATUS_CONFIRMADO)) {
                $result = [
                    'ok' => false,
                    'title' => 'Não foi possível confirmar',
                    'message' => 'O estado atual da marcação não permite confirmação.',
                ];
            } else {
                $calendarEvent->forceFill([
                    'status' => CalendarEvent::STATUS_CONFIRMADO,
                    'cancellation_type' => null,
                    'cancellation_reason' => null,
                    'refund_reserva' => null,
                    'avisou_dentro_prazo' => null,
                ])->save();

                $result = [
                    'ok' => true,
                    'title' => 'Marcação confirmada',
                    'message' => 'Obrigado. A sua marcação ficou confirmada.',
                ];
            }
        }

        return $this->resultView(
            (bool) $result['ok'],
            (string) $result['title'],
            (string) $result['message'],
            (string) ($calendarEvent->store?->slug ?? ''),
            (string) ($calendarEvent->store?->name ?? config('app.name', 'Loja')),
            $targetStatus === CalendarEvent::STATUS_CANCELADO ? 'cancel' : 'confirm'
        );
    }

    /**
     * @return array{ok: bool, event?: CalendarEvent}
     */
    private function resolveLink(string $token): array
    {
        $link = BookingSmsActionLink::query()
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->with([
                'calendarEvent.store:id,slug,name',
                'calendarEvent.user:id,name',
                'calendarEvent.client:id,name,email,phone',
                'calendarEvent.service:id,name',
                'calendarEvent.eventServiceItems.service:id,name',
            ])
            ->first();

        if (! $link || ! $link->calendarEvent || $link->calendarEvent->event_type !== CalendarEvent::TYPE_MARCACAO) {
            return ['ok' => false];
        }

        return [
            'ok' => true,
            'event' => $link->calendarEvent,
        ];
    }

    private function resultView(
        bool $ok,
        string $title,
        string $message,
        string $storeSlug = '',
        string $businessName = 'Loja',
        string $resultType = 'confirm'
    ): View
    {
        return view('booking.sms-action-result', [
            'storeSlug' => $storeSlug,
            'title' => $title,
            'message' => $message,
            'ok' => $ok,
            'businessName' => $businessName,
            'bookingStoreSlug' => $storeSlug !== '' ? $storeSlug : \App\Models\Store::defaultPublicBookingStoreSlug(),
            'resultType' => $resultType,
        ]);
    }
}
