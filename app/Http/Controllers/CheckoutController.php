<?php

namespace App\Http\Controllers;

use App\Http\Concerns\DeniesPrestadorPayments;

use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\Booking;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\ClientWalletTransaction;
use App\Models\CrmSetting;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Notifications\ClientInvoiceAnnulledNotification;
use App\Services\ClientWalletService;
use App\Services\AgendaCheckoutService;
use App\Services\VendusInvoiceEmailService;
use App\Services\VendusInvoiceService;
use App\Support\ApplicableFees;
use App\Support\PhoneDisplay;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class CheckoutController extends Controller
{
    use DeniesPrestadorPayments;

    public function __construct(
        private readonly VendusInvoiceService $vendusInvoiceService,
        private readonly VendusInvoiceEmailService $vendusInvoiceEmailService,
        private readonly ClientWalletService $walletService,
        private readonly AgendaCheckoutService $agendaCheckoutService,
    ) {}

    /**
     * GET agenda/events/{event}/checkout – dados do evento para o checkout.
     */
    public function checkout(CalendarEvent $calendarEvent)
    {
        if ($denied = $this->denyPrestadorPaymentsJson()) {
            return $denied;
        }

        if (($calendarEvent->event_type ?? '') !== CalendarEvent::TYPE_MARCACAO) {
            return response()->json(['error' => 'Apenas marcações podem ir a checkout.'], 422);
        }

        if ($calendarEvent->isMarcacaoStatusLocked()) {
            return response()->json(['error' => 'Esta marcação não pode ser paga.'], 422);
        }

        $requestedEventIds = collect(explode(',', (string) request()->query('event_ids', '')))
            ->map(fn (string $id): int => (int) trim($id))
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $events = $this->agendaCheckoutService->resolveEventsForCheckout($calendarEvent, $requestedEventIds);
        $context = $this->agendaCheckoutService->buildCheckoutContext($events);
        $subtotalItens = (float) ($context['subtotal'] ?? 0);
        $alreadyPaid = (float) ($context['already_paid'] ?? 0);
        $isConsolidated = $events->count() > 1;
        $isPartial = $alreadyPaid > 0.00001 && $alreadyPaid + 0.00001 < $subtotalItens;

        $existingSale = $calendarEvent->sale;
        if (! $isConsolidated && $existingSale && $existingSale->status !== Sale::STATUS_ANULADO && ! $isPartial) {
            $existingSale->load('items');

            return response()->json([
                'event_id' => $calendarEvent->id,
                'client' => $calendarEvent->client ? [
                    'id' => $calendarEvent->client->id,
                    'name' => $calendarEvent->client->name,
                    'email' => $calendarEvent->client->email,
                ] : null,
                'existing_sale' => [
                    'id' => $existingSale->id,
                    'numero_fatura' => $existingSale->numero_fatura,
                    'total' => (float) $existingSale->total,
                    'amount_due' => max(0.0, round($subtotalItens - $alreadyPaid, 2)),
                    'is_partial' => $isPartial,
                    'pdf_url' => route('sales.pdf', $existingSale),
                ],
                'items' => [],
                'selected_event_ids' => $events->pluck('id')->values()->all(),
                'amount_due' => max(0.0, round($subtotalItens - $alreadyPaid, 2)),
                'payment_methods' => Sale::paymentMethods(),
                'wallet_balance_cents' => $this->walletBalanceCentsForClient($calendarEvent->client),
            ]);
        }

        $items = $context['items'];

        return response()->json([
            'event_id' => $calendarEvent->id,
            'selected_event_ids' => $events->pluck('id')->values()->all(),
            'is_consolidated' => $isConsolidated,
            'client' => $calendarEvent->client ? [
                'id' => $calendarEvent->client->id,
                'name' => $calendarEvent->client->name,
                'email' => $calendarEvent->client->email,
            ] : null,
            'apply_catalog_fees' => ! $events->contains(fn (CalendarEvent $event): bool => ApplicableFees::includeCatalogFeesForCalendarEvent($event) === false),
            'existing_sale' => ($existingSale && $existingSale->status !== Sale::STATUS_ANULADO) ? [
                'id' => $existingSale->id,
                'numero_fatura' => $existingSale->numero_fatura,
                'total' => (float) $existingSale->total,
                'amount_due' => max(0.0, round($subtotalItens - $alreadyPaid, 2)),
                'is_partial' => $isPartial,
                'pdf_url' => route('sales.pdf', $existingSale),
            ] : null,
            'items' => $items,
            'amount_due' => max(0.0, round($subtotalItens - $alreadyPaid, 2)),
            'payment_methods' => Sale::paymentMethods(),
            'wallet_balance_cents' => $this->walletBalanceCentsForClient($calendarEvent->client),
        ]);
    }

    /**
     * POST agenda/checkout – criar venda e devolver pdf_url.
     */
    public function store(Request $request)
    {
        if ($denied = $this->denyPrestadorPaymentsJson()) {
            return $denied;
        }

        $validated = $request->validate([
            'event_id' => ['required', 'exists:calendar_events,id'],
            'event_ids' => ['sometimes', 'array', 'min:1'],
            'event_ids.*' => ['integer', 'exists:calendar_events,id'],
            'invoice_only' => ['sometimes', 'boolean'],
            'payment_method' => [Rule::requiredIf(fn (): bool => ! $request->boolean('invoice_only')), 'nullable', 'string', 'in:'.implode(',', array_keys(Sale::paymentMethods()))],
            'invoice_fiscal_mode' => ['required', 'string', 'in:with_nif,consumer'],
            'billing_nif' => ['nullable', 'string', 'max:32'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.tipo' => ['required', 'in:servico,extra,taxa'],
            'items.*.descricao' => ['required', 'string', 'max:255'],
            'items.*.quantidade' => ['required', 'integer', 'min:1'],
            'items.*.preco_unitario' => ['required', 'numeric', 'min:0'],
            'items.*.subtotal' => ['nullable', 'numeric', 'min:0'],
            'items.*.desconto' => ['nullable', 'numeric', 'min:0'],
            'items.*.calendar_event_service_id' => ['nullable', 'exists:calendar_event_services,id'],
            'items.*.service_id' => ['nullable', 'exists:services,id'],
            'items.*.extra_id' => ['nullable', 'exists:extras,id'],
            'items.*.fee_id' => ['nullable', 'exists:fees,id'],
            'gorjeta' => ['nullable', 'numeric', 'min:0'],
            'desconto' => ['nullable', 'numeric', 'min:0'],
            'valor_pago' => ['nullable', 'numeric', 'min:0'],
            'invoice_delivery' => ['nullable', 'string', 'in:email,print'],
            'checkout_mode' => ['sometimes', 'string', 'in:faturar,rascunho'],
        ]);

        $anchorEvent = CalendarEvent::query()
            ->forStore(current_store_id())
            ->findOrFail((int) $validated['event_id']);
        if (($anchorEvent->event_type ?? '') !== CalendarEvent::TYPE_MARCACAO) {
            return response()->json(['error' => 'Apenas marcações podem ir a checkout.'], 422);
        }
        if ($anchorEvent->isMarcacaoStatusLocked()) {
            return response()->json(['error' => 'Esta marcação não pode ser paga.'], 422);
        }

        $events = $this->agendaCheckoutService->resolveEventsForCheckout($anchorEvent, (array) ($validated['event_ids'] ?? []));
        $anchorEvent->loadMissing('client');
        $client = $anchorEvent->client;
        $fiscalMode = (string) ($validated['invoice_fiscal_mode'] ?? 'consumer');
        $billingNifDigits = preg_replace('/\D/', '', (string) ($validated['billing_nif'] ?? ''));
        $clientNif = preg_replace('/\D/', '', (string) ($client?->nif ?? ''));
        if ($fiscalMode === 'with_nif') {
            if (strlen($clientNif) !== 9) {
                if (strlen($billingNifDigits) !== 9) {
                    return response()->json([
                        'error' => 'Para faturar com NIF, indique 9 dígitos na ficha do cliente ou no campo «NIF nesta fatura».',
                    ], 422);
                }
            }
        }

        $subtotalItens = 0.0;
        foreach ($validated['items'] as $row) {
            $qty = (int) $row['quantidade'];
            $preco = (float) $row['preco_unitario'];
            $bruto = round($qty * $preco, 2);
            $descLinha = isset($row['desconto']) ? (float) $row['desconto'] : 0;
            $descLinha = min(max(0, $descLinha), $bruto);
            $subtotalItens += round($bruto - $descLinha, 2);
        }
        $gorjeta = isset($validated['gorjeta']) ? (float) $validated['gorjeta'] : 0;
        if (! CrmSetting::posGorjetaEnabled((int) $anchorEvent->store_id)) {
            $gorjeta = 0.0;
        }
        $descontoDocInput = isset($validated['desconto']) ? max(0, (float) $validated['desconto']) : 0;
        // Re-emitir a fatura final (marcação já paga, fatura anterior anulada): o valor da fatura
        // anulada NÃO conta como «já pago», para podermos voltar a faturar a mesma parte em dívida.
        $invoiceOnly = $request->boolean('invoice_only');
        $itemsForSale = $validated['items'];

        $annulledCaixaSale = $invoiceOnly
            ? Sale::query()
                ->whereIn('calendar_event_id', $events->pluck('id')->all())
                ->where('scope', Sale::SCOPE_CAIXA_LIQUIDACAO)
                ->where('status', Sale::STATUS_ANULADO)
                ->orderByDesc('id')
                ->first()
            : null;

        if ($invoiceOnly && $annulledCaixaSale) {
            // Reproduz exactamente a fatura final anulada (itens, taxas, total, gorjeta, desconto e
            // método de pagamento): o valor original mantém-se, só muda o documento fiscal (ex.: NIF).
            $annulledCaixaSale->loadMissing('items');
            $clonedItems = $annulledCaixaSale->items->map(fn (SaleItem $item): array => [
                'tipo' => $item->tipo,
                'calendar_event_service_id' => $item->calendar_event_service_id,
                'service_id' => $item->service_id,
                'extra_id' => $item->extra_id,
                'fee_id' => $item->fee_id,
                'descricao' => $item->descricao,
                'quantidade' => (int) $item->quantidade,
                'preco_unitario' => (float) $item->preco_unitario,
                'desconto' => $item->desconto !== null ? (float) $item->desconto : 0,
            ])->values()->all();
            if ($clonedItems !== []) {
                $itemsForSale = $clonedItems;
            }
            $total = round((float) $annulledCaixaSale->total, 2);
            $gorjeta = round((float) ($annulledCaixaSale->gorjeta ?? 0), 2);
            $descontoDoc = round((float) ($annulledCaixaSale->desconto ?? 0), 2);
            $paymentMethod = (string) ($annulledCaixaSale->payment_method ?? '');
            // Re-emissão não movimenta a carteira (o débito já ocorreu na fatura original).
            if (! array_key_exists($paymentMethod, Sale::paymentMethods()) || $paymentMethod === Sale::PAYMENT_CREDITOS_CARTEIRA) {
                $paymentMethod = Sale::PAYMENT_OUTRO;
            }
            $valorPago = $total;
        } else {
            $alreadyPaid = (float) $events->sum(fn (CalendarEvent $event): float => ApplicableFees::marcacaoMoneyTowardSubtotal((int) $event->id, ! $invoiceOnly));
            $descontoDoc = max(0, $descontoDocInput);
            $maxDocDisc = $subtotalItens + $gorjeta;
            $descontoDoc = min($descontoDoc, $maxDocDisc);
            $baseAposDesconto = round(max(0, $subtotalItens + $gorjeta - $descontoDoc), 2);
            $total = round(max(0, $baseAposDesconto - $alreadyPaid), 2);
            $paymentMethod = (string) ($validated['payment_method'] ?? '');
            if ($invoiceOnly && ! array_key_exists($paymentMethod, Sale::paymentMethods())) {
                $paymentMethod = Sale::PAYMENT_OUTRO;
            }
            if ($invoiceOnly) {
                $valorPago = $total;
            } else {
                $valorPago = isset($validated['valor_pago']) ? max(0, (float) $validated['valor_pago']) : $total;
                $valorPago = min($valorPago, $total);
            }
        }

        if ($total <= 0.00001) {
            return response()->json(['error' => 'Esta marcação já foi faturada.'], 422);
        }

        $invoiceStatus = $this->resolveInvoiceStatusFromCheckoutMode($validated['checkout_mode'] ?? 'faturar');

        if (! $invoiceOnly && $paymentMethod === Sale::PAYMENT_CREDITOS_CARTEIRA) {
            if (! $client instanceof Client) {
                return response()->json(['error' => 'Esta marcação não tem cliente associado.'], 422);
            }
            $walletCents = $this->walletService->getBalanceCents($client);
            $totalCents = (int) round($total * 100);
            if ($totalCents <= 0) {
                return response()->json(['error' => 'Não há valor em dívida para pagar com créditos.'], 422);
            }
            if ($walletCents < $totalCents) {
                return response()->json([
                    'error' => 'Saldo de créditos insuficiente para liquidar esta marcação.',
                    'wallet_balance_cents' => $walletCents,
                ], 422);
            }
            $valorPago = $total;
        }

        try {
            if ($fiscalMode === 'with_nif' && $client instanceof Client) {
                if (strlen($clientNif) !== 9 && strlen($billingNifDigits) === 9) {
                    $client->nif = $billingNifDigits;
                    $client->save();
                }
            }
            $sale = $this->agendaCheckoutService->persistSale(
                $events,
                $client,
                $itemsForSale,
                $invoiceStatus,
                $paymentMethod,
                $total,
                $valorPago,
                $gorjeta,
                $descontoDoc,
                $fiscalMode === 'consumer',
            );
        } catch (InsufficientWalletBalanceException $e) {
            return response()->json(['error' => 'Saldo de créditos insuficiente.'], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => 'Erro ao gravar a venda.', 'message' => $e->getMessage()], 500);
        }

        $pdfUrl = route('sales.pdf', $sale);
        if ($invoiceStatus !== Sale::INVOICE_STATUS_RASCUNHO) {
            $this->syncSaleWithVendus($sale);
            $sale->refresh();
        }

        $delivery = (string) ($validated['invoice_delivery'] ?? 'print');
        if (! in_array($delivery, ['email', 'print'], true)) {
            $delivery = 'print';
        }

        $emailResult = ['sent' => false, 'message' => null];
        if ($invoiceStatus !== Sale::INVOICE_STATUS_RASCUNHO && $delivery === 'email') {
            $emailResult = $this->vendusInvoiceEmailService->trySendToClient($sale);
        }

        return response()->json([
            'success' => true,
            'sale_id' => $sale->id,
            'numero_fatura' => $sale->numero_fatura,
            'pdf_url' => $pdfUrl,
            'vendus_pdf_url' => $sale->vendus_document_id ? route('sales.vendus.pdf', $sale) : null,
            'vendus_synced' => $sale->vendus_document_id !== null,
            'invoice_status' => $sale->invoice_status,
            'invoice_delivery' => $delivery,
            'invoice_email_sent' => $emailResult['sent'],
            'invoice_email_message' => $emailResult['message'],
            'settled_event_ids' => $events->pluck('id')->values()->all(),
        ]);
    }

    private function resolveInvoiceStatusFromCheckoutMode(?string $checkoutMode): string
    {
        return ($checkoutMode ?? 'faturar') === 'rascunho'
            ? Sale::INVOICE_STATUS_RASCUNHO
            : Sale::INVOICE_STATUS_FATURADO;
    }

    private function syncSaleWithVendus(Sale $sale): void
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

            Log::warning('vendus_invoice_sync_failed', [
                'sale_id' => $sale->id,
                'status' => $result['status'],
                'message' => $result['message'],
            ]);
        } catch (\Throwable $e) {
            $sale->forceFill([
                'vendus_sync_status' => 'error',
                'vendus_sync_error' => $e->getMessage(),
            ])->save();

            Log::error('vendus_invoice_sync_exception', [
                'sale_id' => $sale->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * POST agenda/checkout/mbway/intent – cria pedido MB WAY Stripe para o valor em falta.
     */
    public function createMbwayIntent(Request $request)
    {
        if ($denied = $this->denyPrestadorPaymentsJson()) {
            return $denied;
        }

        if (! CrmSetting::onlineBookingPaymentRequired(current_store_id())) {
            return response()->json([
                'error' => 'Pagamentos automáticos estão desativados. Registe o MB WAY manualmente na caixa.',
            ], 422);
        }

        $validated = $request->validate([
            'event_id' => ['required', 'exists:calendar_events,id'],
            'event_ids' => ['sometimes', 'array', 'min:1'],
            'event_ids.*' => ['integer', 'exists:calendar_events,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.quantidade' => ['required', 'integer', 'min:1'],
            'items.*.preco_unitario' => ['required', 'numeric', 'min:0'],
            'items.*.desconto' => ['nullable', 'numeric', 'min:0'],
            'gorjeta' => ['nullable', 'numeric', 'min:0'],
            'mbway_phone' => ['nullable', 'string', 'max:40'],
        ]);

        $anchorEvent = CalendarEvent::query()
            ->forStore(current_store_id())
            ->with(['client'])
            ->findOrFail((int) $validated['event_id']);
        if (($anchorEvent->event_type ?? '') !== CalendarEvent::TYPE_MARCACAO) {
            return response()->json(['error' => 'Apenas marcações podem ir a checkout.'], 422);
        }
        if ($anchorEvent->isMarcacaoStatusLocked()) {
            return response()->json(['error' => 'Esta marcação não pode ser paga.'], 422);
        }
        $events = $this->agendaCheckoutService->resolveEventsForCheckout($anchorEvent, (array) ($validated['event_ids'] ?? []));

        $subtotalItens = $this->checkoutSubtotalFromItems($validated['items']);
        $gorjeta = isset($validated['gorjeta']) ? max(0, (float) $validated['gorjeta']) : 0.0;
        if (! CrmSetting::posGorjetaEnabled((int) $anchorEvent->store_id)) {
            $gorjeta = 0.0;
        }
        $alreadyPaid = (float) $events->sum(fn (CalendarEvent $event): float => ApplicableFees::marcacaoMoneyTowardSubtotal((int) $event->id));
        $servicesDue = max(0.0, round($subtotalItens - $alreadyPaid, 2));
        $amountDue = round($servicesDue + $gorjeta, 2);
        if ($amountDue <= 0) {
            return response()->json(['error' => 'Não existe valor em falta para cobrar por MB WAY.'], 422);
        }

        $client = $anchorEvent->client;
        if (! $client) {
            return response()->json(['error' => 'A marcação não tem cliente associado.'], 422);
        }

        $rawPhone = trim((string) ($client->phone ?? ''));
        if ($rawPhone === '') {
            $rawPhone = trim((string) ($validated['mbway_phone'] ?? ''));
        }
        $phoneE164 = PhoneDisplay::toE164($rawPhone);
        if (! is_string($phoneE164) || $phoneE164 === '' || ! preg_match('/^\+3519\d{8}$/', $phoneE164)) {
            return response()->json([
                'error' => 'O número MB WAY do cliente é inválido. Indique um telemóvel português válido (ex.: +3519XXXXXXXX).',
            ], 422);
        }

        if (trim((string) ($client->phone ?? '')) === '') {
            $client->phone = $phoneE164;
            $client->save();
        }

        $secret = config('stripe.secret');
        if (! is_string($secret) || $secret === '') {
            return response()->json(['error' => 'Stripe não configurado no servidor.'], 503);
        }
        $this->configureStripeSdk();

        $currency = strtolower((string) config('booking.currency', 'eur'));
        $amountCents = (int) round($amountDue * 100);
        try {
            $intent = PaymentIntent::create([
                'amount' => $amountCents,
                'currency' => $currency,
                'payment_method_types' => ['mb_way'],
                'confirm' => true,
                'payment_method_data' => [
                    'type' => 'mb_way',
                    'billing_details' => [
                        'name' => (string) ($client->name ?? ''),
                        'email' => (string) ($client->email ?? ''),
                        'phone' => $phoneE164,
                    ],
                ],
                'description' => 'Pagamento MB WAY em agenda — '.config('app.name'),
                'metadata' => [
                    'agenda_event_id' => (string) $anchorEvent->id,
                    'agenda_anchor_event_id' => (string) $anchorEvent->id,
                    'agenda_checkout_mode' => $events->count() > 1 ? 'consolidated' : 'single',
                    'agenda_event_ids' => $events->pluck('id')->implode(','),
                ],
            ]);
        } catch (ApiErrorException $e) {
            Log::warning('Stripe MB WAY PaymentIntent::create falhou no checkout da agenda.', [
                'event_id' => $anchorEvent->id,
                'client_id' => $client->id,
                'stripe_code' => $e->getStripeCode(),
                'message' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Não foi possível gerar o pedido MB WAY.'], 422);
        }

        return response()->json([
            'success' => true,
            'payment_intent_id' => $intent->id,
            'status' => (string) $intent->status,
            'amount_due' => $amountDue,
            'booking_paid' => $alreadyPaid,
            'phone' => $phoneE164,
            'selected_event_ids' => $events->pluck('id')->values()->all(),
            'message' => 'Pedido MB WAY enviado para o cliente.',
        ]);
    }

    /**
     * POST agenda/checkout/mbway/finalize – após confirmação Stripe, cria a venda.
     */
    public function finalizeMbway(Request $request)
    {
        if ($denied = $this->denyPrestadorPaymentsJson()) {
            return $denied;
        }

        if (! CrmSetting::onlineBookingPaymentRequired(current_store_id())) {
            return response()->json([
                'error' => 'Pagamentos automáticos estão desativados. Registe o MB WAY manualmente na caixa.',
            ], 422);
        }

        $validated = $request->validate([
            'payment_intent_id' => ['required', 'string', 'max:255'],
            'event_id' => ['required', 'exists:calendar_events,id'],
            'event_ids' => ['sometimes', 'array', 'min:1'],
            'event_ids.*' => ['integer', 'exists:calendar_events,id'],
            'invoice_fiscal_mode' => ['required', 'string', 'in:with_nif,consumer'],
            'billing_nif' => ['nullable', 'string', 'max:32'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.tipo' => ['required', 'in:servico,extra,taxa'],
            'items.*.descricao' => ['required', 'string', 'max:255'],
            'items.*.quantidade' => ['required', 'integer', 'min:1'],
            'items.*.preco_unitario' => ['required', 'numeric', 'min:0'],
            'items.*.subtotal' => ['nullable', 'numeric', 'min:0'],
            'items.*.desconto' => ['nullable', 'numeric', 'min:0'],
            'items.*.calendar_event_service_id' => ['nullable', 'exists:calendar_event_services,id'],
            'items.*.service_id' => ['nullable', 'exists:services,id'],
            'items.*.extra_id' => ['nullable', 'exists:extras,id'],
            'items.*.fee_id' => ['nullable', 'exists:fees,id'],
            'gorjeta' => ['nullable', 'numeric', 'min:0'],
            'invoice_delivery' => ['nullable', 'string', 'in:email,print'],
            'checkout_mode' => ['sometimes', 'string', 'in:faturar,rascunho'],
        ]);

        $this->configureStripeSdk();
        try {
            $intent = PaymentIntent::retrieve((string) $validated['payment_intent_id']);
        } catch (ApiErrorException) {
            return response()->json(['error' => 'Não foi possível validar o pagamento MB WAY.'], 422);
        }

        if ((string) ($intent->status ?? '') !== 'succeeded') {
            return response()->json([
                'success' => false,
                'status' => (string) ($intent->status ?? 'unknown'),
                'message' => 'Pagamento MB WAY ainda não confirmado.',
            ], 202);
        }

        $anchorEvent = CalendarEvent::query()
            ->forStore(current_store_id())
            ->findOrFail((int) $validated['event_id']);
        $events = $this->agendaCheckoutService->resolveEventsForCheckout($anchorEvent, (array) ($validated['event_ids'] ?? []));
        $subtotalItens = $this->checkoutSubtotalFromItems($validated['items']);
        $gorjeta = isset($validated['gorjeta']) ? max(0, (float) $validated['gorjeta']) : 0.0;
        if (! CrmSetting::posGorjetaEnabled((int) $anchorEvent->store_id)) {
            $gorjeta = 0.0;
        }
        $alreadyPaid = (float) $events->sum(fn (CalendarEvent $event): float => ApplicableFees::marcacaoMoneyTowardSubtotal((int) $event->id));
        $amountDue = round(max(0.0, ($subtotalItens - $alreadyPaid) + $gorjeta), 2);
        $intentAmountEur = round(((int) ($intent->amount ?? 0)) / 100, 2);
        if (abs($intentAmountEur - $amountDue) > 0.009) {
            return response()->json(['error' => 'Valor do pagamento MB WAY não coincide com o total selecionado.'], 422);
        }

        $validated['payment_method'] = Sale::PAYMENT_MBWAY;
        $forward = Request::create('/agenda/checkout', 'POST', $validated);
        $forward->setUserResolver(fn () => $request->user());

        return $this->store($forward);
    }

    private function checkoutSubtotalFromItems(array $items): float
    {
        $subtotalItens = 0.0;
        foreach ($items as $row) {
            $qty = (int) ($row['quantidade'] ?? 0);
            $preco = (float) ($row['preco_unitario'] ?? 0);
            $bruto = round(max(0, $qty) * max(0, $preco), 2);
            $descLinha = isset($row['desconto']) ? (float) $row['desconto'] : 0;
            $descLinha = min(max(0, $descLinha), $bruto);
            $subtotalItens += round($bruto - $descLinha, 2);
        }

        return round(max(0, $subtotalItens), 2);
    }

    private function checkoutSubtotalFromEvent(CalendarEvent $calendarEvent, bool $withCatalogFees = false): float
    {
        $subtotal = ApplicableFees::servicesExtrasSubtotalFromEventItems($calendarEvent->eventServiceItems);
        if ($withCatalogFees && ApplicableFees::includeCatalogFeesForCalendarEvent($calendarEvent)) {
            $subtotal = round($subtotal + ApplicableFees::sumPrices(
                ApplicableFees::forServiceIds($calendarEvent->eventServiceItems->pluck('service_id'), (int) $calendarEvent->store_id)
            ), 2);
        }

        return round(max(0.0, $subtotal), 2);
    }

    private function configureStripeSdk(): void
    {
        Stripe::setApiKey((string) config('stripe.secret'));
        $apiVersion = config('stripe.api_version');
        if (! is_string($apiVersion) || $apiVersion === '') {
            return;
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}\.[a-zA-Z0-9_]+$/', $apiVersion)) {
            return;
        }

        Stripe::setApiVersion($apiVersion);
    }

    /**
     * GET sales/{sale}/pdf – devolver PDF da fatura (stream no browser ou descarga).
     */
    public function pdf(Request $request, Sale $sale)
    {
        if ($denied = $this->denyPrestadorPaymentsJson()) {
            return $denied;
        }

        $sale->load(['client', 'items', 'calendarEvent.eventServiceItems.extras.extra']);

        $pdf = Pdf::loadView('pdf.invoice', ['sale' => $sale])
            ->setPaper('a4', 'portrait');

        $safeFilename = str_replace(['/', '\\'], '-', $sale->numero_fatura).'-fatura-recibo.pdf';

        if ($request->boolean('download')) {
            return $pdf->download($safeFilename);
        }

        return $pdf->stream($safeFilename);
    }

    /**
     * GET sales/{sale}/vendus-pdf – devolve o PDF real do documento na Vendus.
     */
    public function vendusPdf(Sale $sale)
    {
        if ($denied = $this->denyPrestadorPaymentsJson()) {
            return $denied;
        }

        if ((int) $sale->store_id !== (int) current_store_id()) {
            abort(404);
        }

        if (! $sale->vendus_document_id) {
            return response()->json(['error' => 'Venda ainda sem documento Vendus sincronizado.'], 422);
        }

        $binary = $this->vendusInvoiceEmailService->fetchVendusInvoicePdfBinaryWithRetry($sale);
        if ($binary === null || $binary === '') {
            return response()->json([
                'error' => 'Nao foi possivel obter o PDF oficial da Vendus para este documento.',
            ], 502);
        }

        $documentId = (int) $sale->vendus_document_id;

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="vendus-document-'.$documentId.'.pdf"',
        ]);
    }

    /**
     * GET sales/{sale}/credit-note-pdf – PDF oficial da nota de crédito da venda anulada.
     */
    public function creditNotePdf(Sale $sale)
    {
        if ($denied = $this->denyPrestadorPaymentsJson()) {
            return $denied;
        }

        if ((int) $sale->store_id !== (int) current_store_id()) {
            abort(404);
        }

        if (! $sale->hasCreditNote()) {
            return response()->json(['error' => 'Esta venda não tem nota de crédito associada.'], 422);
        }

        $binary = $this->vendusInvoiceEmailService->fetchVendusCreditNotePdfBinaryWithRetry($sale);
        if ($binary === null || $binary === '') {
            return response()->json([
                'error' => 'Não foi possível obter o PDF da nota de crédito na Vendus.',
            ], 502);
        }

        $documentId = (int) $sale->vendus_credit_note_id;

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="nota-credito-'.$documentId.'.pdf"',
        ]);
    }

    /**
     * POST sales/{sale}/finalize-invoice — passar venda de rascunho para faturado (Vendus + email opcional).
     */
    public function finalizeInvoice(Request $request, Sale $sale)
    {
        if ($denied = $this->denyPrestadorPaymentsJson()) {
            return $denied;
        }

        $validated = $request->validate([
            'invoice_fiscal_mode' => ['required', 'string', 'in:with_nif,consumer'],
            'billing_nif' => ['nullable', 'string', 'max:32'],
            'invoice_delivery' => ['nullable', 'string', 'in:email,print'],
        ]);

        if ((int) $sale->store_id !== (int) current_store_id()) {
            abort(404);
        }

        if ($sale->status === Sale::STATUS_ANULADO) {
            return response()->json(['error' => 'Esta venda foi anulada.'], 422);
        }

        if (! $sale->isInvoiceDraft()) {
            return response()->json(['error' => 'Esta fatura já foi emitida.'], 422);
        }

        $sale->loadMissing('client');
        $client = $sale->client;
        $fiscalMode = (string) ($validated['invoice_fiscal_mode'] ?? 'consumer');
        $billingNifDigits = preg_replace('/\D/', '', (string) ($validated['billing_nif'] ?? ''));
        $clientNif = preg_replace('/\D/', '', (string) ($client?->nif ?? ''));

        if ($fiscalMode === 'with_nif') {
            if (strlen($clientNif) !== 9 && strlen($billingNifDigits) !== 9) {
                return response()->json([
                    'error' => 'Para faturar com NIF, indique 9 dígitos na ficha do cliente ou no campo «NIF nesta fatura».',
                ], 422);
            }
        }

        try {
            DB::beginTransaction();

            if ($fiscalMode === 'with_nif' && $client instanceof Client) {
                if (strlen($clientNif) !== 9 && strlen($billingNifDigits) === 9) {
                    $client->nif = $billingNifDigits;
                    $client->save();
                }
                $sale->issue_without_fiscal_id = false;
            } else {
                $sale->issue_without_fiscal_id = true;
            }

            $sale->invoice_status = Sale::INVOICE_STATUS_FATURADO;
            $sale->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return response()->json(['error' => 'Erro ao atualizar a fatura.', 'message' => $e->getMessage()], 500);
        }

        $this->syncSaleWithVendus($sale);
        $sale->refresh();

        $delivery = (string) ($validated['invoice_delivery'] ?? 'print');
        if (! in_array($delivery, ['email', 'print'], true)) {
            $delivery = 'print';
        }

        $emailResult = ['sent' => false, 'message' => null];
        if ($delivery === 'email') {
            $emailResult = $this->vendusInvoiceEmailService->trySendToClient($sale);
        }

        return response()->json([
            'success' => true,
            'sale_id' => $sale->id,
            'scope' => $sale->scope,
            'numero_fatura' => $sale->numero_fatura,
            'pdf_url' => route('sales.pdf', $sale),
            'vendus_pdf_url' => $sale->vendus_document_id ? route('sales.vendus.pdf', $sale) : null,
            'vendus_synced' => $sale->vendus_document_id !== null,
            'invoice_status' => $sale->invoice_status,
            'invoice_delivery' => $delivery,
            'invoice_email_sent' => $emailResult['sent'],
            'invoice_email_message' => $emailResult['message'],
        ]);
    }

    /**
     * POST sales/{sale}/revert – anular a venda e desbloquear a marcação para edição.
     *
     * Com final_invoice_only: só a fatura final de caixa (agenda).
     * Sem final_invoice_only: todas as vendas da marcação excepto booking_reserva — o valor de reserva online
     * não deve ser anulado nem gerar NC por engano (ex.: relatório de vendas); reembolso de reserva é excepção manual na Vendus.
     */
    public function revert(Request $request, Sale $sale)
    {
        if ($denied = $this->denyPrestadorPaymentsJson()) {
            return $denied;
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'final_invoice_only' => ['sometimes', 'boolean'],
        ], [
            'reason.required' => 'Indique a razão da anulação.',
        ]);

        if ($sale->status === Sale::STATUS_ANULADO) {
            return response()->json(['error' => 'Esta venda já foi anulada.'], 422);
        }

        $calendarEvent = $sale->calendarEvent;
        if (! $calendarEvent) {
            return response()->json(['error' => 'Venda sem marcação associada.'], 422);
        }

        $reason = trim((string) ($validated['reason'] ?? ''));
        $finalInvoiceOnly = $request->boolean('final_invoice_only');

        if ($finalInvoiceOnly) {
            if ($sale->scope !== Sale::SCOPE_CAIXA_LIQUIDACAO) {
                return response()->json([
                    'error' => 'Só pode anular desta forma a fatura final (pagamento em loja). A fatura de pré-pagamento mantém-se.',
                ], 422);
            }
            if ((int) $sale->calendar_event_id !== (int) $calendarEvent->id) {
                return response()->json(['error' => 'Venda não corresponde a esta marcação.'], 422);
            }
            $salesToRevert = Sale::query()
                ->whereKey($sale->id)
                ->where('status', '!=', Sale::STATUS_ANULADO)
                ->get();
        } else {
            $salesToRevert = Sale::query()
                ->where('calendar_event_id', $calendarEvent->id)
                ->where('status', '!=', Sale::STATUS_ANULADO)
                ->where('scope', '!=', Sale::SCOPE_BOOKING_RESERVA)
                ->orderBy('id')
                ->get();
        }

        $settledEventIds = DB::table('sale_calendar_events')
            ->whereIn('sale_id', $salesToRevert->pluck('id')->all())
            ->pluck('calendar_event_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        if ($settledEventIds->isEmpty()) {
            $settledEventIds = collect([(int) $calendarEvent->id]);
        }

        if ($salesToRevert->isEmpty()) {
            return response()->json(['error' => 'Não existem vendas ativas para anular nesta marcação.'], 422);
        }

        $invoiceLabelsForEmail = $salesToRevert
            ->map(fn (Sale $s) => $s->invoiceListLabel())
            ->values()
            ->all();

        $creditNotes = [];
        $creditNoteIdBySale = [];
        foreach ($salesToRevert as $candidateSale) {
            if ($candidateSale->scope === Sale::SCOPE_BOOKING_RESERVA) {
                continue;
            }
            if (! $candidateSale->vendus_document_id) {
                continue;
            }

            $cnResult = $this->vendusInvoiceService->createCreditNoteForSale($candidateSale, $reason);
            if (! ($cnResult['ok'] ?? false)) {
                return response()->json([
                    'error' => 'Não foi possível gerar a nota de crédito na Vendus para a venda '.$candidateSale->numero_fatura.'.',
                    'details' => $cnResult['message'] ?? 'Erro desconhecido na Vendus.',
                ], 422);
            }

            $creditNoteId = $cnResult['credit_note_id'] ?? null;
            if ($creditNoteId !== null) {
                $creditNoteIdBySale[(int) $candidateSale->id] = (int) $creditNoteId;
            }

            $creditNotes[] = [
                'sale_id' => $candidateSale->id,
                'sale_numero_fatura' => $candidateSale->numero_fatura,
                'vendus_document_id' => $candidateSale->vendus_document_id,
                'vendus_credit_note_id' => $creditNoteId,
            ];
        }

        try {
            DB::beginTransaction();
            $cancelledAt = now();
            foreach ($salesToRevert as $candidateSale) {
                if ($candidateSale->scope === Sale::SCOPE_BOOKING_RESERVA) {
                    continue;
                }
                $candidateSale->update([
                    'status' => Sale::STATUS_ANULADO,
                    'vendus_credit_note_id' => $creditNoteIdBySale[(int) $candidateSale->id] ?? $candidateSale->vendus_credit_note_id,
                    'cancelled_at' => $cancelledAt,
                    'cancellation_reason' => $reason !== '' ? $reason : null,
                ]);
            }
            // Anular só a fatura final mantém a marcação «paga» (completo): o cliente continua
            // a dever o documento fiscal, por isso repomos o aviso amarelo e a opção de voltar a faturar.
            if (! $finalInvoiceOnly) {
                CalendarEvent::query()
                    ->whereIn('id', $settledEventIds->all())
                    ->update([
                        'status' => CalendarEvent::STATUS_TERMINADO,
                        'cancellation_type' => null,
                        'cancellation_reason' => null,
                    ]);
                $calendarEvent->refresh();
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return response()->json(['error' => 'Erro ao reverter a venda.', 'message' => $e->getMessage()], 500);
        }

        Log::info('sale_reverted', [
            'sale_id' => $sale->id,
            'calendar_event_id' => $calendarEvent->id,
            'reverted_sales_count' => $salesToRevert->count(),
            'reverted_sale_ids' => $salesToRevert->pluck('id')->values()->all(),
            'reason' => $reason,
            'vendus_credit_notes' => $creditNotes,
            'user_id' => auth()->id(),
            'final_invoice_only' => $finalInvoiceOnly,
            'settled_event_ids' => $settledEventIds->all(),
        ]);

        $this->notifyClientInvoiceAnnulled((int) $calendarEvent->id, $invoiceLabelsForEmail);

        $message = $finalInvoiceOnly
            ? 'Fatura final anulada (nota de crédito gerada, se aplicável). A marcação mantém-se paga; pode emitir uma nova fatura final.'
            : 'Venda(s) anulada(s). A marcação ficou em estado Terminado.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'event_id' => $calendarEvent->id,
            'reverted_sales_count' => $salesToRevert->count(),
            'final_invoice_only' => $finalInvoiceOnly,
        ]);
    }

    /**
     * POST agenda/events/{calendarEvent}/invoices/email — envia por email o PDF Vendus de todas as vendas activas desta marcação.
     */
    public function sendMarcacaoInvoicesEmail(CalendarEvent $calendarEvent)
    {
        if ($denied = $this->denyPrestadorPaymentsJson()) {
            return $denied;
        }

        $calendarEvent = CalendarEvent::query()
            ->forStore(current_store_id())
            ->whereKey($calendarEvent->id)
            ->firstOrFail();
        if (($calendarEvent->event_type ?? '') !== CalendarEvent::TYPE_MARCACAO) {
            return response()->json(['error' => 'Apenas marcações suportam este envio.'], 422);
        }

        $sales = Sale::query()
            ->where('calendar_event_id', $calendarEvent->id)
            ->where('status', '!=', Sale::STATUS_ANULADO)
            ->orderBy('id')
            ->get();

        if ($sales->isEmpty()) {
            return response()->json(['error' => 'Não há faturas activas para enviar.'], 422);
        }

        $sent = 0;
        $skipped = 0;
        $lastMessage = null;
        foreach ($sales as $s) {
            if (! $s->vendus_document_id) {
                $skipped++;

                continue;
            }
            $res = $this->vendusInvoiceEmailService->trySendToClient($s);
            if ($res['sent'] ?? false) {
                $sent++;
            } else {
                $lastMessage = $res['message'] ?? 'Falha no envio.';
            }
        }

        if ($sent === 0) {
            return response()->json([
                'success' => false,
                'error' => $lastMessage ?? 'Não foi possível enviar faturas por email (sem documento Vendus ou erro de envio).',
                'sent' => 0,
                'skipped' => $skipped,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $sent === 1
                ? 'Fatura enviada por email ao cliente.'
                : ($sent.' faturas enviadas por email ao cliente.'),
            'sent' => $sent,
            'skipped' => $skipped,
        ]);
    }

    /**
     * Email ao cliente após anulação de fatura(s), alinhado com CalendarController / BookingPaymentController.
     *
     * @param  list<string>  $invoiceLabels
     */
    private function notifyClientInvoiceAnnulled(int $calendarEventId, array $invoiceLabels): void
    {
        $event = CalendarEvent::query()->with('client')->find($calendarEventId);
        if (! $event || ($event->event_type ?? '') !== CalendarEvent::TYPE_MARCACAO) {
            return;
        }

        $client = $event->client;
        if (! $this->clientAllowsEmailBookingUpdates($client)) {
            return;
        }

        $email = trim((string) ($client?->email ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Notification::route('mail', $this->resolveClientNotificationRecipientEmail($email))
                ->notify(new ClientInvoiceAnnulledNotification($calendarEventId, $invoiceLabels));
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar email de fatura anulada ao cliente.', [
                'calendar_event_id' => $calendarEventId,
                'client_email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Evita enviar emails de testes para clientes reais (igual CalendarController).
     */
    private function resolveClientNotificationRecipientEmail(?string $originalEmail): string
    {
        $originalEmail = $originalEmail ?? '';
        $supportEmail = env('MAIL_CLIENT_TEST_REDIRECT_TO', 'suporte@softrace.pt');

        if (app()->environment('production')) {
            return $originalEmail;
        }

        return $supportEmail;
    }

    private function clientAllowsEmailBookingUpdates(?Client $client): bool
    {
        if (! $client instanceof Client) {
            return false;
        }

        return (bool) ($client->notify_email_booking_updates ?? true);
    }

    private function walletBalanceCentsForClient(?Client $client): int
    {
        if (! $client instanceof Client) {
            return 0;
        }

        return $this->walletService->getBalanceCents($client);
    }
}
