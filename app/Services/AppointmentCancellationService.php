<?php

namespace App\Services;

use App\Exceptions\AppointmentCancellationException;
use App\Models\Booking;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\ClientWalletTransaction;
use App\Notifications\ClientAppointmentCancelledNotification;
use App\Support\BookingLocale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class AppointmentCancellationService
{
    public function __construct(
        private CancellationPolicyService $policyService,
        private ClientWalletService $walletService,
    ) {}

    /**
     * Cancela uma marcação aplicando a política de aviso e crédito na carteira quando aplicável.
     *
     * @param  array{
     *     cancellation_reason?: string|null,
     *     cancellation_type?: string|null,
     *     notify_client?: bool,
     *     block_if_outside_notice_period?: bool,
     *     created_by_type?: string,
     *     created_by_user_id?: int|null,
     * }  $options
     */
    public function cancel(CalendarEvent $event, array $options = []): AppointmentCancellationResult
    {
        $event = $this->loadCancellationRelations($event);

        if (($event->event_type ?? '') !== CalendarEvent::TYPE_MARCACAO) {
            throw new AppointmentCancellationException(
                'Apenas marcações podem ser canceladas por este fluxo.',
                AppointmentCancellationException::NOT_MARCACAO,
            );
        }

        if ($event->isMarcacaoStatusLocked()) {
            $status = (string) ($event->status ?? '');

            if ($status === CalendarEvent::STATUS_CANCELADO) {
                return new AppointmentCancellationResult(
                    event: $event,
                    policy: $this->policyService->resolveForEvent($event),
                    walletCredited: (int) ($event->wallet_credit_amount_cents ?? 0) > 0,
                    walletCreditAmountCents: (int) ($event->wallet_credit_amount_cents ?? 0),
                    alreadyCancelled: true,
                );
            }

            throw new AppointmentCancellationException(
                'Esta marcação já não pode ser cancelada.',
                AppointmentCancellationException::STATUS_LOCKED,
            );
        }

        $policy = $this->policyService->resolveForEvent($event);

        $blockOutside = (bool) ($options['block_if_outside_notice_period'] ?? false);
        if ($blockOutside && ! $policy->isWithinNoticePeriod) {
            throw new AppointmentCancellationException(
                'Já não é possível cancelar sem perder o pré-pagamento online.',
                AppointmentCancellationException::OUTSIDE_NOTICE_PERIOD,
            );
        }

        $reason = trim((string) ($options['cancellation_reason'] ?? ''));
        $cancellationType = (string) ($options['cancellation_type'] ?? CalendarEvent::STATUS_CANCELADO);
        $createdByType = (string) ($options['created_by_type'] ?? ClientWalletTransaction::CREATED_BY_SYSTEM);
        $createdByUserId = isset($options['created_by_user_id']) ? (int) $options['created_by_user_id'] : null;

        $walletTransaction = null;
        $walletCreditCents = 0;
        $refundReserva = false;

        return DB::transaction(function () use (
            $event,
            $policy,
            $reason,
            $cancellationType,
            $createdByType,
            $createdByUserId,
            $options,
            &$walletTransaction,
            &$walletCreditCents,
            &$refundReserva,
        ): AppointmentCancellationResult {
            $locked = CalendarEvent::query()
                ->whereKey($event->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked = $this->loadCancellationRelations($locked);

            if ($locked->isMarcacaoStatusLocked()) {
                if ((string) ($locked->status ?? '') === CalendarEvent::STATUS_CANCELADO) {
                    return new AppointmentCancellationResult(
                        event: $locked,
                        policy: $policy,
                        walletCredited: (int) ($locked->wallet_credit_amount_cents ?? 0) > 0,
                        walletCreditAmountCents: (int) ($locked->wallet_credit_amount_cents ?? 0),
                        alreadyCancelled: true,
                    );
                }

                throw new AppointmentCancellationException(
                    'Esta marcação já não pode ser cancelada.',
                    AppointmentCancellationException::STATUS_LOCKED,
                );
            }

            if ($policy->isWithinNoticePeriod && $policy->hasPaidDeposit && $policy->eligibleDepositCreditCents > 0) {
                $client = $locked->client;
                if ($client instanceof Client) {
                    $booking = $locked->onlineBooking;
                    $creditCents = $this->resolveCreditCents($policy->eligibleDepositCreditCents, $booking);

                    if ($creditCents > 0) {
                        $walletTransaction = $this->walletService->credit(
                            $client,
                            $creditCents,
                            ClientWalletTransaction::TYPE_CREDIT_CANCELLATION_IN_POLICY,
                            ClientWalletService::idempotencyKeyForCancellation((int) $locked->id),
                            [
                                'calendar_event_id' => $locked->id,
                                'booking_id' => $booking?->id,
                                'description' => $this->walletCreditDescription($locked),
                                'metadata' => [
                                    'notice_hours' => $policy->noticeHoursApplied,
                                    'within_notice_period' => true,
                                ],
                                'created_by_type' => $createdByType,
                                'created_by_user_id' => $createdByUserId,
                            ],
                        );
                        $walletCreditCents = $creditCents;
                        $refundReserva = true;
                    }
                }
            }

            $locked->forceFill([
                'status' => CalendarEvent::STATUS_CANCELADO,
                'cancellation_type' => $cancellationType,
                'cancellation_reason' => $reason !== '' ? $reason : null,
                'avisou_dentro_prazo' => $policy->isWithinNoticePeriod,
                'refund_reserva' => $refundReserva,
                'cancellation_evaluated_at' => $policy->evaluatedAtLocal->copy()->utc(),
                'cancellation_notice_hours_applied' => $policy->noticeHoursApplied,
                'wallet_credit_amount_cents' => $walletCreditCents > 0 ? $walletCreditCents : null,
            ])->save();

            if ((bool) ($options['notify_client'] ?? false)) {
                $this->notifyClient($locked);
            }

            return new AppointmentCancellationResult(
                event: $locked->fresh(['client', 'onlineBooking', 'store']),
                policy: $policy,
                walletCredited: $walletCreditCents > 0,
                walletCreditAmountCents: $walletCreditCents,
                walletTransaction: $walletTransaction,
            );
        });
    }

    private function loadCancellationRelations(CalendarEvent $event): CalendarEvent
    {
        $event->loadMissing(['client', 'onlineBooking', 'store']);

        return $event;
    }

    private function resolveCreditCents(int $eligibleCents, ?Booking $booking): int
    {
        if ($eligibleCents <= 0) {
            return 0;
        }

        if (! $booking instanceof Booking) {
            return $eligibleCents;
        }

        $paidCents = (int) round(((float) ($booking->paid_amount ?? 0)) * 100);

        return min($eligibleCents, max(0, $paidCents));
    }

    private function walletCreditDescription(CalendarEvent $event): string
    {
        $start = $event->start_at
            ? $event->start_at->copy()->timezone(
                $this->policyService->businessTimezoneForStore((int) ($event->store_id ?? 0) ?: null)
            )->format('d/m/Y H:i')
            : '';

        return $start !== ''
            ? 'Crédito por cancelamento da marcação de '.$start
            : 'Crédito por cancelamento da marcação';
    }

    private function notifyClient(CalendarEvent $event): void
    {
        if (! $event->shouldSendBookingNotifications()) {
            return;
        }

        $client = $event->client;
        $email = $client?->email;

        if (
            ! $client
            || ! (bool) ($client->notify_email_booking_updates ?? true)
            || ! $email
            || ! filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            return;
        }

        try {
            Notification::locale(BookingLocale::emailLocale())
                ->route('mail', $email)
                ->notify(new ClientAppointmentCancelledNotification($event->id));
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar email de cancelamento ao cliente.', [
                'calendar_event_id' => $event->id,
                'client_email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
