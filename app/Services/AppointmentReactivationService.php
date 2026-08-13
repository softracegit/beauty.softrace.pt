<?php

namespace App\Services;

use App\Exceptions\AppointmentReactivationException;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\ClientWalletTransaction;
use App\Notifications\ClientAppointmentReactivatedNotification;
use App\Support\ActivityLogContext;
use App\Support\BookingLocale;
use App\Support\StoreBusinessTime;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class AppointmentReactivationService
{
    public function __construct(
        private ClientWalletService $walletService,
    ) {}

    /**
     * @return list<string>
     */
    public function blockers(CalendarEvent $event): array
    {
        $blockers = [];

        if ($this->isStartInThePast($event)) {
            $blockers[] = 'Só é possível reativar marcações com início no momento atual ou no futuro. Esta marcação está no passado — crie uma nova na agenda.';
        }

        if ($this->hasCancellationWalletCredit($event)) {
            $amount = (int) ($event->wallet_credit_amount_cents ?? 0);
            if ($amount <= 0) {
                $txn = $this->walletService->assertIdempotency(
                    ClientWalletService::idempotencyKeyForCancellation((int) $event->id),
                );
                $amount = $txn ? (int) $txn->amount_cents : 0;
            }
            $euros = number_format($amount / 100, 2, ',', ' ');
            $blockers[] = $amount > 0
                ? "Foi creditado {$euros} € na carteira do cliente por este cancelamento. Não é possível reativar."
                : 'Foi creditado valor na carteira do cliente por este cancelamento. Não é possível reativar.';
        }

        if ($this->hasScheduleConflict($event)) {
            $blockers[] = 'O horário já está ocupado por outra marcação ou tempo pessoal do mesmo técnico.';
        }

        return $blockers;
    }

    /**
     * @param  array{
     *     reactivation_reason?: string|null,
     *     notify_client?: bool,
     * }  $options
     */
    public function reactivate(CalendarEvent $event, array $options = []): AppointmentReactivationResult
    {
        $event->loadMissing(['client', 'store']);

        if (($event->event_type ?? '') !== CalendarEvent::TYPE_MARCACAO) {
            throw new AppointmentReactivationException(
                'Apenas marcações podem ser reativadas.',
                AppointmentReactivationException::NOT_MARCACAO,
            );
        }

        $status = (string) ($event->status ?? '');
        if (! in_array($status, [CalendarEvent::STATUS_CANCELADO, CalendarEvent::STATUS_FALTOU], true)) {
            throw new AppointmentReactivationException(
                'Só é possível reativar marcações canceladas ou com falta.',
                AppointmentReactivationException::STATUS_NOT_ELIGIBLE,
            );
        }

        $blockers = $this->blockers($event);
        if ($blockers !== []) {
            throw new AppointmentReactivationException(
                $blockers[0],
                AppointmentReactivationException::BLOCKED,
                $blockers,
            );
        }

        $reason = trim((string) ($options['reactivation_reason'] ?? ''));
        $notifyClient = (bool) ($options['notify_client'] ?? false);

        $result = DB::transaction(function () use ($event, $reason): AppointmentReactivationResult {
            $locked = CalendarEvent::query()
                ->whereKey($event->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->loadMissing(['client', 'store']);

            $previousStatus = (string) ($locked->status ?? '');
            if (! in_array($previousStatus, [CalendarEvent::STATUS_CANCELADO, CalendarEvent::STATUS_FALTOU], true)) {
                throw new AppointmentReactivationException(
                    'Só é possível reativar marcações canceladas ou com falta.',
                    AppointmentReactivationException::STATUS_NOT_ELIGIBLE,
                );
            }

            $blockers = $this->blockers($locked);
            if ($blockers !== []) {
                throw new AppointmentReactivationException(
                    $blockers[0],
                    AppointmentReactivationException::BLOCKED,
                    $blockers,
                );
            }

            $locked->disableLogging();
            $locked->forceFill([
                'status' => CalendarEvent::STATUS_AGENDADO,
                'cancellation_type' => null,
                'cancellation_reason' => null,
                'refund_reserva' => false,
                'avisou_dentro_prazo' => null,
                'cancellation_evaluated_at' => null,
                'cancellation_notice_hours_applied' => null,
                'wallet_credit_amount_cents' => null,
            ])->save();
            $locked->enableLogging();

            $properties = [
                'attributes' => [
                    'status' => CalendarEvent::STATUS_AGENDADO,
                ],
                'old' => [
                    'status' => $previousStatus,
                ],
                'previous_status' => $previousStatus,
                'new_status' => CalendarEvent::STATUS_AGENDADO,
                'reactivation_reason' => $reason !== '' ? $reason : null,
            ];
            $contextLine = ActivityLogContext::marcacaoLine($locked);
            if ($contextLine !== null) {
                $properties['contexto'] = $contextLine;
            }

            activity()
                ->performedOn($locked)
                ->causedBy(auth()->user())
                ->withProperties($properties)
                ->event('updated')
                ->log('Marcação reativada');

            return new AppointmentReactivationResult(
                event: $locked->fresh(['client', 'store', 'user', 'eventServiceItems.service']),
                previousStatus: $previousStatus,
                clientNotified: false,
            );
        });

        $clientNotified = false;
        if ($notifyClient) {
            $clientNotified = $this->notifyClient($result->event);
        }

        return new AppointmentReactivationResult(
            event: $result->event,
            previousStatus: $result->previousStatus,
            clientNotified: $clientNotified,
        );
    }

    /**
     * Reativar mantém start_at/end_at; não faz sentido voltar a «agendado» no passado.
     */
    public function isStartInThePast(CalendarEvent $event): bool
    {
        $start = $event->start_at;
        if (! $start instanceof CarbonInterface) {
            return false;
        }

        $storeId = (int) ($event->store_id ?? 0);
        $nowUtc = $storeId > 0
            ? StoreBusinessTime::nowUtcForStore($storeId)
            : now()->utc();

        return StoreBusinessTime::toUtcInstant($start)->lt($nowUtc);
    }

    public function hasCancellationWalletCredit(CalendarEvent $event): bool
    {
        if ((int) ($event->wallet_credit_amount_cents ?? 0) > 0) {
            return true;
        }

        if ((bool) ($event->refund_reserva ?? false)) {
            return true;
        }

        $existing = $this->walletService->assertIdempotency(
            ClientWalletService::idempotencyKeyForCancellation((int) $event->id),
        );
        if ($existing instanceof ClientWalletTransaction) {
            return true;
        }

        return ClientWalletTransaction::query()
            ->where('calendar_event_id', $event->id)
            ->where('type', ClientWalletTransaction::TYPE_CREDIT_CANCELLATION_IN_POLICY)
            ->exists();
    }

    public function hasScheduleConflict(CalendarEvent $event): bool
    {
        $userId = (int) ($event->user_id ?? 0);
        $start = $event->start_at;
        $end = $event->end_at;

        if ($userId <= 0 || ! $start instanceof CarbonInterface || ! $end instanceof CarbonInterface) {
            return false;
        }

        return CalendarEvent::query()
            ->where('user_id', $userId)
            ->whereKeyNot($event->id)
            ->where(function ($q): void {
                $q->whereNull('status')
                    ->orWhereNotIn('status', [
                        CalendarEvent::STATUS_CANCELADO,
                        CalendarEvent::STATUS_ANULADO,
                        CalendarEvent::STATUS_FALTOU,
                    ]);
            })
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->exists();
    }

    private function notifyClient(CalendarEvent $event): bool
    {
        if (! $event->shouldSendBookingNotifications()) {
            return false;
        }

        $client = $event->client;
        $email = $client?->email;

        if (
            ! $client instanceof Client
            || ! (bool) ($client->notify_email_booking_updates ?? true)
            || ! $email
            || ! filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            return false;
        }

        try {
            Notification::route('mail', $email)
                ->notify(
                    (new ClientAppointmentReactivatedNotification($event->id))
                        ->locale(BookingLocale::emailLocale())
                );

            return true;
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar email de reativação ao cliente.', [
                'calendar_event_id' => $event->id,
                'client_email' => $email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
