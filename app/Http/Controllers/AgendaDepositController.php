<?php

namespace App\Http\Controllers;

use App\Http\Concerns\DeniesPrestadorPayments;

use App\Exceptions\AgendaDepositException;
use App\Models\CalendarEvent;
use App\Models\CrmSetting;
use App\Services\AgendaDepositResult;
use App\Services\AgendaDepositService;
use App\Services\VendusInvoiceEmailService;
use App\Services\VendusInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Stripe;

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
            'payment_method' => ['nullable', 'string', 'in:dinheiro,mbway,transferencia'],
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

        $validated = $request->validate([
            'mbway_phone' => ['nullable', 'string', 'max:40'],
            'wallet_apply' => ['sometimes', 'boolean'],
            'wallet_apply_cents' => ['sometimes', 'integer', 'min:0'],
            'custom_amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        try {
            $payload = $this->depositService->createMbwayIntent(
                $calendarEvent,
                (int) ($validated['wallet_apply_cents'] ?? 0),
                $this->optionalCustomAmount($request),
                $validated['mbway_phone'] ?? null,
                $request->boolean('wallet_apply'),
            );
        } catch (AgendaDepositException $e) {
            return $this->depositErrorResponse($e);
        }

        return response()->json(array_merge(['success' => true], $payload));
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
        if (! CrmSetting::onlineBookingPaymentRequired(current_store_id())) {
            return response()->json([
                'error' => 'Pagamentos automáticos estão desativados. Registe o MB WAY manualmente (dinheiro/MB WAY).',
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

        $this->configureStripeSdk();

        try {
            $intent = PaymentIntent::retrieve((string) $validated['payment_intent_id']);
        } catch (ApiErrorException) {
            return response()->json(['error' => 'Não foi possível validar o pagamento MB WAY.'], 422);
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

        return $this->successResponse($result, (string) ($validated['invoice_delivery'] ?? 'print'));
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
        if (! CrmSetting::onlineBookingPaymentRequired(current_store_id())) {
            return response()->json([
                'error' => 'Pagamentos com cartão guardado requerem pagamentos online ativos nas definições.',
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

    private function configureStripeSdk(): void
    {
        $secret = config('stripe.secret');
        if (! is_string($secret) || $secret === '') {
            return;
        }

        Stripe::setApiKey($secret);
        $apiVersion = config('stripe.api_version');
        if (is_string($apiVersion) && $apiVersion !== '' && preg_match('/^\d{4}-\d{2}-\d{2}\.[a-zA-Z0-9_]+$/', $apiVersion)) {
            Stripe::setApiVersion($apiVersion);
        }
    }
}
