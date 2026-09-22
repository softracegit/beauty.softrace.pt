<?php

namespace App\Services;

use App\Exceptions\AgendaDepositException;
use App\Http\Controllers\CheckoutController;
use App\Models\AgendaMbwayPendingPayment;
use App\Models\CalendarEvent;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Support\CurrentStore;
use App\Support\StripeCredentials;
use App\Support\StripeMbwayWaitStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Throwable;

/**
 * Persistência + conclusão de pagamentos MB Way da agenda (caixa / pré-pagamento)
 * quando o browser já não está a fazer poll (webhook Stripe).
 */
class AgendaMbwayPendingService
{
    public function __construct(
        private readonly AgendaDepositService $depositService,
        private readonly VendusInvoiceService $vendusInvoiceService,
        private readonly VendusInvoiceEmailService $vendusInvoiceEmailService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function remember(
        string $paymentIntentId,
        string $flow,
        int $storeId,
        int $calendarEventId,
        ?int $staffUserId,
        array $payload,
    ): AgendaMbwayPendingPayment {
        return AgendaMbwayPendingPayment::query()->updateOrCreate(
            ['stripe_payment_intent_id' => $paymentIntentId],
            [
                'flow' => $flow,
                'store_id' => $storeId,
                'calendar_event_id' => $calendarEventId,
                'staff_user_id' => $staffUserId,
                'status' => AgendaMbwayPendingPayment::STATUS_PENDING,
                'payload' => $payload,
                'sale_id' => null,
                'completed_at' => null,
            ],
        );
    }

    public function markCanceled(string $paymentIntentId): void
    {
        AgendaMbwayPendingPayment::query()
            ->where('stripe_payment_intent_id', $paymentIntentId)
            ->where('status', AgendaMbwayPendingPayment::STATUS_PENDING)
            ->update([
                'status' => AgendaMbwayPendingPayment::STATUS_CANCELED,
                'completed_at' => now(),
            ]);
    }

    public function markCompleted(string $paymentIntentId, ?int $saleId = null): void
    {
        AgendaMbwayPendingPayment::query()
            ->where('stripe_payment_intent_id', $paymentIntentId)
            ->whereIn('status', [
                AgendaMbwayPendingPayment::STATUS_PENDING,
                AgendaMbwayPendingPayment::STATUS_CANCELED,
                AgendaMbwayPendingPayment::STATUS_FAILED,
            ])
            ->update([
                'status' => AgendaMbwayPendingPayment::STATUS_COMPLETED,
                'sale_id' => $saleId,
                'completed_at' => now(),
            ]);
    }

    /**
     * Poll leve para o modal MB Way: BD local primeiro (webhook), Stripe só se ainda pendente.
     *
     * @return array{
     *     state: string,
     *     success?: bool,
     *     terminal?: bool,
     *     sale_id?: int|null,
     *     status?: string,
     *     message?: string,
     *     completed_via_webhook?: bool,
     *     http: int
     * }
     */
    public function pollForBrowser(string $paymentIntentId, int $expectedEventId): array
    {
        $pending = AgendaMbwayPendingPayment::query()
            ->where('stripe_payment_intent_id', $paymentIntentId)
            ->first();

        if ($pending instanceof AgendaMbwayPendingPayment
            && (int) $pending->calendar_event_id !== $expectedEventId) {
            return [
                'state' => 'error',
                'success' => false,
                'message' => 'Pedido MB Way não corresponde a esta marcação.',
                'http' => 422,
            ];
        }

        if ($pending?->status === AgendaMbwayPendingPayment::STATUS_COMPLETED) {
            return [
                'state' => 'completed',
                'success' => true,
                'sale_id' => $pending->sale_id,
                'completed_via_webhook' => true,
                'message' => 'Pagamento MB Way confirmado.',
                'http' => 200,
            ];
        }

        if ($pending && in_array($pending->status, [
            AgendaMbwayPendingPayment::STATUS_CANCELED,
            AgendaMbwayPendingPayment::STATUS_FAILED,
        ], true)) {
            return [
                'state' => 'terminal',
                'success' => false,
                'terminal' => true,
                'status' => (string) $pending->status,
                'message' => 'O cliente recusou ou o pagamento MB Way foi cancelado.',
                'http' => 409,
            ];
        }

        $storeId = $pending instanceof AgendaMbwayPendingPayment
            ? (int) $pending->store_id
            : (int) current_store_id();

        $store = Store::query()->find($storeId);
        if ($store instanceof Store) {
            app(CurrentStore::class)->set($store);
        }

        try {
            StripeCredentials::configureSdk($storeId);
            $intent = PaymentIntent::retrieve($paymentIntentId);
        } catch (ApiErrorException|\RuntimeException) {
            return [
                'state' => 'error',
                'success' => false,
                'message' => 'Não foi possível validar o pagamento MB Way.',
                'http' => 422,
            ];
        }

        $metaEventId = (int) ($intent->metadata['agenda_event_id']
            ?? $intent->metadata['agenda_anchor_event_id']
            ?? $intent->metadata['event_id']
            ?? 0);
        if ($metaEventId > 0 && $metaEventId !== $expectedEventId) {
            return [
                'state' => 'error',
                'success' => false,
                'message' => 'Pedido MB Way não corresponde a esta marcação.',
                'http' => 422,
            ];
        }

        $wait = StripeMbwayWaitStatus::fromIntent($intent);

        if ($wait['outcome'] === StripeMbwayWaitStatus::OUTCOME_SUCCEEDED) {
            if ($pending instanceof AgendaMbwayPendingPayment) {
                try {
                    $this->completePending($pending);
                    $pending->refresh();
                } catch (Throwable $e) {
                    Log::warning('agenda_mbway_poll_complete_failed', [
                        'payment_intent_id' => $paymentIntentId,
                        'message' => $e->getMessage(),
                    ]);

                    return [
                        'state' => 'ready_to_finalize',
                        'success' => false,
                        'status' => 'succeeded',
                        'message' => 'Pagamento confirmado. A finalizar…',
                        'http' => 202,
                    ];
                }

                if ($pending->status === AgendaMbwayPendingPayment::STATUS_COMPLETED) {
                    return [
                        'state' => 'completed',
                        'success' => true,
                        'sale_id' => $pending->sale_id,
                        'message' => 'Pagamento MB Way confirmado.',
                        'http' => 200,
                    ];
                }
            }

            return [
                'state' => 'ready_to_finalize',
                'success' => false,
                'status' => 'succeeded',
                'message' => 'Pagamento confirmado. A finalizar…',
                'http' => 202,
            ];
        }

        if ($wait['outcome'] === StripeMbwayWaitStatus::OUTCOME_TERMINAL) {
            $this->markCanceled($paymentIntentId);

            return [
                'state' => 'terminal',
                'success' => false,
                'terminal' => true,
                'status' => $wait['status'],
                'message' => $wait['message'],
                'http' => 409,
            ];
        }

        return [
            'state' => 'waiting',
            'success' => false,
            'status' => $wait['status'],
            'message' => $wait['message'],
            'http' => 202,
        ];
    }

    /**
     * Chamado pelo webhook Stripe após payment_intent.succeeded.
     *
     * Também recupera casos em que o staff cancelou a espera no CRM mas o cliente
     * confirmou na mesma no telemóvel (o dinheiro entrou no Stripe).
     */
    public function completeFromWebhook(string $paymentIntentId): void
    {
        $pending = AgendaMbwayPendingPayment::query()
            ->where('stripe_payment_intent_id', $paymentIntentId)
            ->first();

        if (! $pending instanceof AgendaMbwayPendingPayment) {
            return;
        }

        if ($pending->status === AgendaMbwayPendingPayment::STATUS_COMPLETED) {
            return;
        }

        if (! $this->isCompletableStatus((string) $pending->status)) {
            return;
        }

        try {
            $this->completePending($pending);
        } catch (Throwable $e) {
            Log::error('agenda_mbway_webhook_complete_failed', [
                'payment_intent_id' => $paymentIntentId,
                'flow' => $pending->flow,
                'event_id' => $pending->calendar_event_id,
                'prior_status' => $pending->status,
                'message' => $e->getMessage(),
            ]);

            AgendaMbwayPendingPayment::query()
                ->whereKey($pending->id)
                ->whereIn('status', [
                    AgendaMbwayPendingPayment::STATUS_PENDING,
                    AgendaMbwayPendingPayment::STATUS_CANCELED,
                ])
                ->update(['status' => AgendaMbwayPendingPayment::STATUS_FAILED]);
        }
    }

    public function completePending(AgendaMbwayPendingPayment $pending): void
    {
        /** @var array{sale_id: int, invoice_delivery: string}|null $depositSideEffects */
        $depositSideEffects = null;

        DB::transaction(function () use ($pending, &$depositSideEffects): void {
            /** @var AgendaMbwayPendingPayment|null $locked */
            $locked = AgendaMbwayPendingPayment::query()
                ->whereKey($pending->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || $locked->status === AgendaMbwayPendingPayment::STATUS_COMPLETED) {
                return;
            }

            if (! $this->isCompletableStatus((string) $locked->status)) {
                return;
            }

            $store = Store::query()->find($locked->store_id);
            if (! $store instanceof Store) {
                throw new \RuntimeException('Loja do pedido MB Way não encontrada.');
            }

            app(CurrentStore::class)->set($store);
            StripeCredentials::configureSdk((int) $store->id);

            try {
                $intent = PaymentIntent::retrieve($locked->stripe_payment_intent_id);
            } catch (ApiErrorException $e) {
                throw new \RuntimeException('Não foi possível validar o PaymentIntent MB Way.', 0, $e);
            }

            if ((string) ($intent->status ?? '') !== 'succeeded') {
                return;
            }

            if ($locked->status !== AgendaMbwayPendingPayment::STATUS_PENDING) {
                Log::info('agenda_mbway_webhook_recover_after_dismiss', [
                    'payment_intent_id' => $locked->stripe_payment_intent_id,
                    'prior_status' => $locked->status,
                    'flow' => $locked->flow,
                    'event_id' => $locked->calendar_event_id,
                ]);
            }

            $saleId = null;
            if ($locked->flow === AgendaMbwayPendingPayment::FLOW_DEPOSIT) {
                $saleId = $this->completeDeposit($locked, $intent);
            } else {
                $saleId = $this->completeCheckout($locked, $intent);
            }

            $locked->forceFill([
                'status' => AgendaMbwayPendingPayment::STATUS_COMPLETED,
                'sale_id' => $saleId,
                'completed_at' => now(),
            ])->save();

            if ($locked->flow === AgendaMbwayPendingPayment::FLOW_DEPOSIT && $saleId) {
                $payload = is_array($locked->payload) ? $locked->payload : [];
                $depositSideEffects = [
                    'sale_id' => $saleId,
                    'invoice_delivery' => (string) ($payload['invoice_delivery'] ?? 'print'),
                ];
            }
        });

        // Fora da transação: HTTP à Vendus não deve segurar o lock do pending.
        if ($depositSideEffects !== null) {
            $this->syncDepositSaleAfterBackgroundComplete(
                $depositSideEffects['sale_id'],
                $depositSideEffects['invoice_delivery'],
            );
        }
    }

    private function isCompletableStatus(string $status): bool
    {
        return in_array($status, [
            AgendaMbwayPendingPayment::STATUS_PENDING,
            AgendaMbwayPendingPayment::STATUS_CANCELED,
            AgendaMbwayPendingPayment::STATUS_FAILED,
        ], true);
    }

    /**
     * Espelha AgendaDepositController::successResponse (Vendus + email) no path webhook/poll.
     */
    private function syncDepositSaleAfterBackgroundComplete(int $saleId, string $invoiceDelivery): void
    {
        $sale = Sale::query()->find($saleId);
        if (! $sale instanceof Sale || $sale->isInvoiceDraft()) {
            return;
        }

        try {
            $result = $this->vendusInvoiceService->syncSale($sale);
            if ($result['ok']) {
                $sale->forceFill([
                    'vendus_sync_status' => 'synced',
                    'vendus_document_id' => $result['document_id'],
                    'vendus_synced_at' => now(),
                    'vendus_sync_error' => null,
                ])->save();
            } else {
                $sale->forceFill([
                    'vendus_sync_status' => 'error',
                    'vendus_sync_error' => $result['message'],
                ])->save();

                Log::warning('vendus_invoice_sync_failed_agenda_deposit_webhook', [
                    'sale_id' => $sale->id,
                    'status' => $result['status'],
                    'message' => $result['message'],
                ]);
            }
        } catch (Throwable $e) {
            $sale->forceFill([
                'vendus_sync_status' => 'error',
                'vendus_sync_error' => $e->getMessage(),
            ])->save();

            Log::error('vendus_invoice_sync_exception_agenda_deposit_webhook', [
                'sale_id' => $sale->id,
                'message' => $e->getMessage(),
            ]);
        }

        $delivery = in_array($invoiceDelivery, ['email', 'print'], true) ? $invoiceDelivery : 'print';
        if ($delivery !== 'email') {
            return;
        }

        $sale->refresh();
        if ($sale->isInvoiceDraft()) {
            return;
        }

        try {
            $this->vendusInvoiceEmailService->trySendToClient($sale);
        } catch (Throwable $e) {
            Log::warning('vendus_invoice_email_failed_agenda_deposit_webhook', [
                'sale_id' => $sale->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function completeDeposit(AgendaMbwayPendingPayment $pending, PaymentIntent $intent): ?int
    {
        $event = CalendarEvent::query()->find($pending->calendar_event_id);
        if (! $event instanceof CalendarEvent) {
            throw new \RuntimeException('Marcação do pré-pagamento MB Way não encontrada.');
        }

        $payload = is_array($pending->payload) ? $pending->payload : [];

        try {
            $result = $this->depositService->collectAfterMbwayIntent($event, $intent, [
                'invoice_fiscal_mode' => (string) ($payload['invoice_fiscal_mode'] ?? 'consumer'),
                'billing_nif' => $payload['billing_nif'] ?? null,
                'custom_amount' => isset($payload['custom_amount']) ? (float) $payload['custom_amount'] : null,
                'staff_user_id' => $pending->staff_user_id,
                'checkout_mode' => $payload['checkout_mode'] ?? 'faturar',
            ]);
        } catch (AgendaDepositException $e) {
            if ($e->httpStatus === 202) {
                return null;
            }
            throw $e;
        }

        return $result->sale?->id;
    }

    private function completeCheckout(AgendaMbwayPendingPayment $pending, PaymentIntent $intent): ?int
    {
        $payload = is_array($pending->payload) ? $pending->payload : [];
        $payload['payment_intent_id'] = $pending->stripe_payment_intent_id;
        $payload['payment_method'] = \App\Models\Sale::PAYMENT_MBWAY;
        $payload['event_id'] = $pending->calendar_event_id;

        $staff = $pending->staff_user_id
            ? User::query()->find($pending->staff_user_id)
            : null;

        $request = Request::create('/agenda/checkout/mbway/finalize', 'POST', $payload);
        $request->headers->set('Accept', 'application/json');
        if ($staff instanceof User) {
            \Illuminate\Support\Facades\Auth::setUser($staff);
            $request->setUserResolver(fn () => $staff);
        }

        /** @var CheckoutController $checkout */
        $checkout = app(CheckoutController::class);
        $response = $checkout->finalizeMbway($request);
        $data = $response->getData(true);

        if (is_array($data) && ! empty($data['success'])) {
            return isset($data['sale_id']) ? (int) $data['sale_id'] : null;
        }

        $status = $response->getStatusCode();
        if ($status === 202) {
            return null;
        }

        $message = is_array($data)
            ? (string) ($data['error'] ?? $data['message'] ?? 'Falha ao finalizar checkout MB Way.')
            : 'Falha ao finalizar checkout MB Way.';

        // Já faturada / race com o poll do browser: tratar como sucesso idempotente.
        if ($status === 422 && str_contains(mb_strtolower($message), 'já foi faturada')) {
            return null;
        }

        throw new \RuntimeException($message);
    }
}
