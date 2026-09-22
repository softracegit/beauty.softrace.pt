<?php

namespace App\Http\Controllers;

use App\Http\Concerns\DeniesPrestadorPayments;

use App\Exceptions\AgendaDepositException;
use App\Models\AgendaMbwayPendingPayment;
use App\Models\CalendarEvent;
use App\Models\CrmSetting;
use App\Models\Sale;
use App\Services\AgendaDepositResult;
use App\Services\AgendaDepositService;
use App\Services\AgendaMbwayPendingService;
use App\Services\VendusInvoiceEmailService;
use App\Services\VendusInvoiceService;
use App\Support\PaymentMethodCatalog;
use App\Support\StripeCredentials;
use App\Support\StripeMbwayWaitStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;

class AgendaDepositController extends Controller
{
    use DeniesPrestadorPayments;

    public function __construct(
        private readonly AgendaDepositService $depositService,
        private readonly VendusInvoiceService $vendusInvoiceService,
        private readonly VendusInvoiceEmailService $vendusInvoiceEmailService,
    ) {}

    /**
     * GET agenda/events/{calendarEvent}/deposit — pré-visualização da reserva.
     */
    public function show(Request $request, CalendarEvent $calendarEvent): JsonResponse
    {
        if ($denied = $this->denyPrestadorPaymentsJson()) {
            return $denied;
        }

        $this->assertMarcacaoInStore($calendarEvent);

        $customAmount = $this->optionalCustomAmount($request);

        try {
            $preview = $this->depositService->preview($calendarEvent, $customAmount);
        } catch (AgendaDepositException $e) {
            return $this->depositErrorResponse($e);
        }

        return response()->json(array_merge($preview, [
            'event_id' => $calendarEvent->id,
        ]));
    }

    /**
     * POST agenda/events/{calendarEvent}/deposit — dinheiro e/ou créditos (carteira).
     */
    public function store(Request $request, CalendarEvent $calendarEvent): JsonResponse
    {
        if ($denied = $this->denyPrestadorPaymentsJson()) {
            return $denied;
        }

        $this->assertMarcacaoInStore($calendarEvent);

        $validated = $request->validate([
            'payment_method' => ['nullable', 'string', 'in:dinheiro,mbway,mbway_manual,transferencia'],
            'invoice_fiscal_mode' => ['required', 'string', 'in:with_nif,consumer'],
            'billing_nif' => ['nullable', 'string', 'max:32'],
            'invoice_delivery' => ['nullable', 'string', 'in:email,print'],
            'wallet_apply' => ['sometimes', 'boolean'],
            'wallet_apply_cents' => ['sometimes', 'integer', 'min:0'],
            'custom_amount' => ['nullable', 'numeric', 'min:0.01'],
            'checkout_mode' => ['sometimes', 'string', 'in:faturar,rascunho'],
        ]);

        try {
            $result = $this->depositService->collectWithCashAndWallet($calendarEvent, [
                'payment_method' => $validated['payment_method'] ?? null,
                'invoice_fiscal_mode' => (string) $validated['invoice_fiscal_mode'],
                'billing_nif' => $validated['billing_nif'] ?? null,
                'wallet_apply' => $request->boolean('wallet_apply'),
                'wallet_apply_cents' => (int) ($validated['wallet_apply_cents'] ?? 0),
                'custom_amount' => isset($validated['custom_amount']) ? (float) $validated['custom_amount'] : null,
                'staff_user_id' => auth()->id(),
                'checkout_mode' => $validated['checkout_mode'] ?? 'faturar',
            ]);
        } catch (AgendaDepositException $e) {
            return $this->depositErrorResponse($e);
        }

        return $this->successResponse($result, (string) ($validated['invoice_delivery'] ?? 'print'));
    }

    /**
     * POST agenda/events/{calendarEvent}/deposit/mbway/intent
     */
    public function createMbwayIntent(Request $request, CalendarEvent $calendarEvent): JsonResponse
    {
        if ($denied = $this->denyPrestadorPaymentsJson()) {
            return $denied;
        }

        $this->assertMarcacaoInStore($calendarEvent);

        $storeId = (int) ($calendarEvent->store_id ?: current_store_id());
        if (! PaymentMethodCatalog::isEnabled(Sale::PAYMENT_MBWAY, PaymentMethodCatalog::CHANNEL_AGENDA, $storeId)
            || ! StripeCredentials::isReady($storeId)) {
            return response()->json([
                'error' => 'MB Way (Stripe) não está disponível para pré-pagamento. Verifique Definições → Pagamentos.',
            ], 422);
        }

        $validated = $request->validate([
            'mbway_phone' => ['nullable', 'string', 'max:40'],
            'wallet_apply' => ['sometimes', 'boolean'],
            'wallet_apply_cents' => ['sometimes', 'integer', 'min:0'],
            'custom_amount' => ['nullable', 'numeric', 'min:0.01'],
            'invoice_fiscal_mode' => ['sometimes', 'string', 'in:with_nif,consumer'],
            'billing_nif' => ['nullable', 'string', 'max:32'],
            'invoice_delivery' => ['nullable', 'string', 'in:email,print'],
            'checkout_mode' => ['sometimes', 'string', 'in:faturar,rascunho'],
        ]);

        $customAmount = $this->optionalCustomAmount($request);

        try {
            $payload = $this->depositService->createMbwayIntent(
                $calendarEvent,
                (int) ($validated['wallet_apply_cents'] ?? 0),
                $customAmount,
                $validated['mbway_phone'] ?? null,
                $request->boolean('wallet_apply'),
            );
        } catch (AgendaDepositException $e) {
            return $this->depositErrorResponse($e);
        }

        if (! empty($payload['payment_intent_id'])) {
            app(AgendaMbwayPendingService::class)->remember(
                (string) $payload['payment_intent_id'],
                AgendaMbwayPendingPayment::FLOW_DEPOSIT,
                (int) $calendarEvent->store_id,
                (int) $calendarEvent->id,
                auth()->id() ? (int) auth()->id() : null,
                [
                    'invoice_fiscal_mode' => (string) ($validated['invoice_fiscal_mode'] ?? 'consumer'),
                    'billing_nif' => $validated['billing_nif'] ?? null,
                    'custom_amount' => $customAmount,
                    'checkout_mode' => (string) ($validated['checkout_mode'] ?? 'faturar'),
                    'invoice_delivery' => (string) ($validated['invoice_delivery'] ?? 'print'),
                ],
            );
        }

        return response()->json(array_merge(['success' => true], $payload));
    }

    /**
     * POST agenda/events/{calendarEvent}/deposit/mbway/status
     */
    public function mbwayStatus(Request $request, CalendarEvent $calendarEvent): JsonResponse
    {
        if ($denied = $this->denyPrestadorPaymentsJson()) {
            return $denied;
        }

        $this->assertMarcacaoInStore($calendarEvent);

        $validated = $request->validate([
            'payment_intent_id' => ['required', 'string', 'max:255'],
        ]);

        $result = app(AgendaMbwayPendingService::class)->pollForBrowser(
            (string) $validated['payment_intent_id'],
            (int) $calendarEvent->id,
        );
        $http = (int) ($result['http'] ?? 200);
        unset($result['http']);

        return response()->json($result, $http);
    }

    /**
     * POST agenda/events/{calendarEvent}/deposit/mbway/finalize
     */
    public function finalizeMbway(Request $request, CalendarEvent $calendarEvent): JsonResponse
    {
        if ($denied = $this->denyPrestadorPaymentsJson()) {
            return $denied;
        }

        $this->assertMarcacaoInStore($calendarEvent);
        $storeId = current_store_id();
        if (! PaymentMethodCatalog::isEnabled(Sale::PAYMENT_MBWAY, PaymentMethodCatalog::CHANNEL_AGENDA, $storeId)
            || ! StripeCredentials::isReady($storeId)) {
            return response()->json([
                'error' => 'MB Way (Stripe) não está disponível para pré-pagamento. Verifique Definições → Pagamentos.',
            ], 422);
        }

        $validated = $request->validate([
            'payment_intent_id' => ['required', 'string', 'max:255'],
            'invoice_fiscal_mode' => ['required', 'string', 'in:with_nif,consumer'],
            'billing_nif' => ['nullable', 'string', 'max:32'],
            'invoice_delivery' => ['nullable', 'string', 'in:email,print'],
            'custom_amount' => ['nullable', 'numeric', 'min:0.01'],
            'checkout_mode' => ['sometimes', 'string', 'in:faturar,rascunho'],
        ]);

        $alreadyDone = AgendaMbwayPendingPayment::query()
            ->where('stripe_payment_intent_id', (string) $validated['payment_intent_id'])
            ->where('status', AgendaMbwayPendingPayment::STATUS_COMPLETED)
            ->first();
        if ($alreadyDone) {
            return response()->json([
                'success' => true,
                'sale_id' => $alreadyDone->sale_id,
                'message' => 'Pagamento MB WAY já confirmado.',
                'completed_via_webhook' => true,
            ]);
        }

        $this->configureStripeSdk($storeId);
        try {
            $intent = PaymentIntent::retrieve((string) $validated['payment_intent_id']);
        } catch (ApiErrorException) {
            return response()->json(['error' => 'Não foi possível validar o pagamento MB WAY.'], 422);
        }

        $wait = StripeMbwayWaitStatus::fromIntent($intent);
        if ($wait['outcome'] === StripeMbwayWaitStatus::OUTCOME_WAITING) {
            return response()->json([
                'success' => false,
                'status' => $wait['status'],
                'message' => $wait['message'],
            ], 202);
        }
        if ($wait['outcome'] === StripeMbwayWaitStatus::OUTCOME_TERMINAL) {
            app(AgendaMbwayPendingService::class)->markCanceled((string) $validated['payment_intent_id']);

            return response()->json([
                'success' => false,
                'terminal' => true,
                'status' => $wait['status'],
                'message' => $wait['message'],
            ], 409);
        }

        try {
            $result = $this->depositService->collectAfterMbwayIntent($calendarEvent, $intent, [
                'invoice_fiscal_mode' => (string) $validated['invoice_fiscal_mode'],
                'billing_nif' => $validated['billing_nif'] ?? null,
                'custom_amount' => isset($validated['custom_amount']) ? (float) $validated['custom_amount'] : null,
                'staff_user_id' => auth()->id(),
                'checkout_mode' => $validated['checkout_mode'] ?? 'faturar',
            ]);
        } catch (AgendaDepositException $e) {
            return $this->depositErrorResponse($e);
        }

        app(AgendaMbwayPendingService::class)->markCompleted(
            (string) $validated['payment_intent_id'],
            $result->sale?->id,
        );

        return $this->successResponse($result, (string) ($validated['invoice_delivery'] ?? 'print'));
    }

    /**
     * POST agenda/events/{calendarEvent}/deposit/mbway/cancel
     */
    public function cancelMbway(Request $request, CalendarEvent $calendarEvent): JsonResponse
    {
        if ($denied = $this->denyPrestadorPaymentsJson()) {
            return $denied;
        }

        $this->assertMarcacaoInStore($calendarEvent);

        $storeId = (int) ($calendarEvent->store_id ?: current_store_id());
        if (! StripeCredentials::isReady($storeId)) {
            return response()->json([
                'error' => 'Stripe não está pronto. Configure em Definições → Pagamentos.',
            ], 422);
        }

        $validated = $request->validate([
            'payment_intent_id' => ['required', 'string', 'max:255'],
            'cancellation_reason' => ['sometimes', 'string', 'in:requested_by_customer,abandoned'],
        ]);

        $this->configureStripeSdk($storeId);
        try {
            $intent = PaymentIntent::retrieve((string) $validated['payment_intent_id']);
        } catch (ApiErrorException) {
            return response()->json(['error' => 'Não foi possível localizar o pedido MB WAY.'], 422);
        }

        $metaEventId = (int) ($intent->metadata['event_id'] ?? 0);
        if ($metaEventId !== (int) $calendarEvent->id) {
            return response()->json(['error' => 'Pedido MB WAY não corresponde a esta marcação.'], 422);
        }

        $status = (string) ($intent->status ?? '');
        if ($status === 'succeeded') {
            return response()->json([
                'success' => true,
                'status' => 'succeeded',
                'message' => 'O pagamento já foi confirmado.',
            ]);
        }
        if ($status === 'canceled') {
            app(AgendaMbwayPendingService::class)->markCanceled((string) $validated['payment_intent_id']);

            return response()->json([
                'success' => true,
                'status' => 'canceled',
                'message' => 'Pedido MB WAY já estava cancelado.',
            ]);
        }

        $reason = (string) ($validated['cancellation_reason'] ?? 'requested_by_customer');
        $cancelable = in_array($status, [
            'requires_payment_method',
            'requires_confirmation',
            'requires_action',
            'requires_capture',
            'processing',
        ], true);

        if (! $cancelable) {
            return response()->json([
                'success' => false,
                'status' => $status !== '' ? $status : 'unknown',
                'error' => 'O pedido MB WAY já não pode ser cancelado.',
            ], 422);
        }

        try {
            $intent->cancel(['cancellation_reason' => $reason]);
        } catch (ApiErrorException $e) {
            Log::warning('Stripe MB WAY PaymentIntent::cancel falhou no depósito da agenda.', [
                'event_id' => $calendarEvent->id,
                'payment_intent_id' => $intent->id ?? null,
                'stripe_code' => $e->getStripeCode(),
                'message' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Não foi possível cancelar o pedido MB WAY.'], 422);
        }

        app(AgendaMbwayPendingService::class)->markCanceled((string) $validated['payment_intent_id']);

        return response()->json([
            'success' => true,
            'status' => 'canceled',
            'message' => 'Pedido MB WAY cancelado.',
        ]);
    }

    /**
     * POST agenda/events/{calendarEvent}/deposit/card — cartão guardado (off-session).
     */
    public function storeCard(Request $request, CalendarEvent $calendarEvent): JsonResponse
    {
        if ($denied = $this->denyPrestadorPaymentsJson()) {
            return $denied;
        }

        $this->assertMarcacaoInStore($calendarEvent);
        $storeId = current_store_id();
        if (! PaymentMethodCatalog::isEnabled(Sale::PAYMENT_CARTAO, PaymentMethodCatalog::CHANNEL_AGENDA, $storeId)
            || ! StripeCredentials::isReady($storeId)) {
            return response()->json([
                'error' => 'Pagamentos com cartão requerem Stripe activo em Definições → Pagamentos.',
            ], 422);
        }

        $clientId = (int) ($calendarEvent->client_id ?? 0);

        $validated = $request->validate([
            'saved_card_id' => [
                'nullable',
                'integer',
                Rule::exists('booking_saved_cards', 'id')
                    ->where(fn ($query) => $query
                        ->where('client_id', $clientId)
                        ->whereNull('detached_at')),
            ],
            'invoice_fiscal_mode' => ['required', 'string', 'in:with_nif,consumer'],
            'billing_nif' => ['nullable', 'string', 'max:32'],
            'invoice_delivery' => ['nullable', 'string', 'in:email,print'],
            'wallet_apply' => ['sometimes', 'boolean'],
            'wallet_apply_cents' => ['sometimes', 'integer', 'min:0'],
            'custom_amount' => ['nullable', 'numeric', 'min:0.01'],
            'checkout_mode' => ['sometimes', 'string', 'in:faturar,rascunho'],
        ]);

        try {
            $result = $this->depositService->collectWithSavedCard($calendarEvent, [
                'saved_card_id' => isset($validated['saved_card_id']) ? (int) $validated['saved_card_id'] : null,
                'invoice_fiscal_mode' => (string) $validated['invoice_fiscal_mode'],
                'billing_nif' => $validated['billing_nif'] ?? null,
                'wallet_apply' => $request->boolean('wallet_apply'),
                'wallet_apply_cents' => (int) ($validated['wallet_apply_cents'] ?? 0),
                'custom_amount' => isset($validated['custom_amount']) ? (float) $validated['custom_amount'] : null,
                'staff_user_id' => auth()->id(),
                'checkout_mode' => $validated['checkout_mode'] ?? 'faturar',
            ]);
        } catch (AgendaDepositException $e) {
            return $this->depositErrorResponse($e);
        }

        return $this->successResponse($result, (string) ($validated['invoice_delivery'] ?? 'print'));
    }

    private function successResponse(AgendaDepositResult $result, string $invoiceDelivery): JsonResponse
    {
        $sale = $result->sale;
        if ($sale !== null && ! $sale->isInvoiceDraft()) {
            $this->syncSaleWithVendus($sale);
            $sale->refresh();
        }

        $delivery = in_array($invoiceDelivery, ['email', 'print'], true) ? $invoiceDelivery : 'print';
        $emailResult = ['sent' => false, 'message' => null];
        if ($delivery === 'email' && $sale !== null && ! $sale->isInvoiceDraft()) {
            $emailResult = $this->vendusInvoiceEmailService->trySendToClient($sale);
        }

        $payload = [
            'success' => true,
            'booking_id' => $result->booking->id,
            'deposit_amount' => $result->depositAmount,
            'wallet_applied_cents' => $result->walletAppliedCents,
            'stripe_portion_cents' => $result->stripePortionCents,
            'booking_paid_amount' => (float) $result->booking->paid_amount,
            'invoice_delivery' => $delivery,
            'invoice_email_sent' => $emailResult['sent'],
            'invoice_email_message' => $emailResult['message'],
        ];

        if ($sale !== null) {
            $payload['sale_id'] = $sale->id;
            $payload['numero_fatura'] = $sale->numero_fatura;
            $payload['pdf_url'] = route('sales.pdf', $sale);
            $payload['vendus_pdf_url'] = $sale->vendus_document_id ? route('sales.vendus.pdf', $sale) : null;
            $payload['vendus_synced'] = $sale->vendus_document_id !== null;
            $payload['invoice_status'] = $sale->invoice_status;
        } else {
            $payload['sale_id'] = null;
            $payload['pdf_url'] = null;
            $payload['vendus_pdf_url'] = null;
            $payload['vendus_synced'] = false;
        }

        return response()->json($payload);
    }

    private function syncSaleWithVendus(\App\Models\Sale $sale): void
    {
        if ($sale->isInvoiceDraft()) {
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

                return;
            }

            $sale->forceFill([
                'vendus_sync_status' => 'error',
                'vendus_sync_error' => $result['message'],
            ])->save();

            Log::warning('vendus_invoice_sync_failed_agenda_deposit', [
                'sale_id' => $sale->id,
                'status' => $result['status'],
                'message' => $result['message'],
            ]);
        } catch (\Throwable $e) {
            $sale->forceFill([
                'vendus_sync_status' => 'error',
                'vendus_sync_error' => $e->getMessage(),
            ])->save();

            Log::error('vendus_invoice_sync_exception_agenda_deposit', [
                'sale_id' => $sale->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function assertMarcacaoInStore(CalendarEvent $calendarEvent): void
    {
        if ((int) $calendarEvent->store_id !== (int) current_store_id()) {
            abort(404);
        }
        if (($calendarEvent->event_type ?? '') !== CalendarEvent::TYPE_MARCACAO) {
            abort(404);
        }
    }

    private function optionalCustomAmount(Request $request): ?float
    {
        if (! $request->has('custom_amount')) {
            return null;
        }

        $value = $request->input('custom_amount');
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function depositErrorResponse(AgendaDepositException $e): JsonResponse
    {
        $status = $e->httpStatus;
        $body = ['error' => $e->getMessage()];
        if ($status === 202) {
            $body['success'] = false;
        }

        return response()->json($body, $status);
    }

    private function configureStripeSdk(?int $storeId = null): void
    {
        try {
            StripeCredentials::configureSdk($storeId ?? current_store_id());
        } catch (\RuntimeException) {
            // Caller validates readiness where needed.
        }
    }
}
