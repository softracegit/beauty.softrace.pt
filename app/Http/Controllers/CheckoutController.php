<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\CalendarEvent;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\VendusInvoiceService;
use App\Support\PhoneDisplay;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class CheckoutController extends Controller
{
    public function __construct(private readonly VendusInvoiceService $vendusInvoiceService) {}

    /**
     * GET agenda/events/{event}/checkout – dados do evento para o checkout.
     */
    public function checkout(CalendarEvent $calendarEvent)
    {
        if (($calendarEvent->event_type ?? '') !== CalendarEvent::TYPE_MARCACAO) {
            return response()->json(['error' => 'Apenas marcações podem ir a checkout.'], 422);
        }

        if ($calendarEvent->isMarcacaoStatusLocked()) {
            return response()->json(['error' => 'Esta marcação não pode ser paga.'], 422);
        }

        $calendarEvent->load(['client', 'eventServiceItems.service', 'eventServiceItems.extras.extra']);
        $subtotalItens = $this->checkoutSubtotalFromEvent($calendarEvent);
        $salesPaid = (float) Sale::query()
            ->where('calendar_event_id', $calendarEvent->id)
            ->where('status', '!=', Sale::STATUS_ANULADO)
            ->sum(DB::raw('COALESCE(valor_pago, total)'));
        $bookingPaid = (float) Booking::query()
            ->where('calendar_event_id', $calendarEvent->id)
            ->where('payment_status', Booking::PAYMENT_PAID)
            ->orderByDesc('id')
            ->value('paid_amount');
        $alreadyPaid = round(max($salesPaid, $bookingPaid, 0.0), 2);
        $isPartial = $alreadyPaid > 0.00001 && $alreadyPaid + 0.00001 < $subtotalItens;

        $existingSale = $calendarEvent->sale;
        if ($existingSale && $existingSale->status !== Sale::STATUS_ANULADO && ! $isPartial) {
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
                'payment_methods' => Sale::paymentMethods(),
            ]);
        }

        $items = [];
        $sortOrder = 0;
        foreach ($calendarEvent->eventServiceItems as $esi) {
            $items[] = [
                'tipo' => SaleItem::TIPO_SERVICO,
                'calendar_event_service_id' => $esi->id,
                'service_id' => $esi->service_id,
                'extra_id' => null,
                'descricao' => $esi->service?->name ?? 'Serviço',
                'quantidade' => 1,
                'preco_unitario' => (float) $esi->price,
                'subtotal' => (float) $esi->price,
                'sort_order' => $sortOrder++,
            ];
            foreach ($esi->extras as $ex) {
                $price = (float) ($ex->price ?? $ex->extra?->price ?? 0);
                $items[] = [
                    'tipo' => SaleItem::TIPO_EXTRA,
                    'calendar_event_service_id' => $esi->id,
                    'service_id' => null,
                    'extra_id' => $ex->extra_id,
                    'descricao' => '+ '.($ex->extra?->name ?? 'Extra'),
                    'quantidade' => 1,
                    'preco_unitario' => $price,
                    'subtotal' => $price,
                    'sort_order' => $sortOrder++,
                ];
            }
        }

        return response()->json([
            'event_id' => $calendarEvent->id,
            'client' => $calendarEvent->client ? [
                'id' => $calendarEvent->client->id,
                'name' => $calendarEvent->client->name,
                'email' => $calendarEvent->client->email,
            ] : null,
            'existing_sale' => ($existingSale && $existingSale->status !== Sale::STATUS_ANULADO) ? [
                'id' => $existingSale->id,
                'numero_fatura' => $existingSale->numero_fatura,
                'total' => (float) $existingSale->total,
                'amount_due' => max(0.0, round($subtotalItens - $alreadyPaid, 2)),
                'is_partial' => $isPartial,
                'pdf_url' => route('sales.pdf', $existingSale),
            ] : null,
            'items' => $items,
            'payment_methods' => Sale::paymentMethods(),
        ]);
    }

    /**
     * POST agenda/checkout – criar venda e devolver pdf_url.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => ['required', 'exists:calendar_events,id'],
            'payment_method' => ['required', 'string', 'in:'.implode(',', array_keys(Sale::paymentMethods()))],
            'items' => ['required', 'array', 'min:1'],
            'items.*.tipo' => ['required', 'in:servico,extra'],
            'items.*.descricao' => ['required', 'string', 'max:255'],
            'items.*.quantidade' => ['required', 'integer', 'min:1'],
            'items.*.preco_unitario' => ['required', 'numeric', 'min:0'],
            'items.*.subtotal' => ['nullable', 'numeric', 'min:0'],
            'items.*.desconto' => ['nullable', 'numeric', 'min:0'],
            'items.*.calendar_event_service_id' => ['nullable', 'exists:calendar_event_services,id'],
            'items.*.service_id' => ['nullable', 'exists:services,id'],
            'items.*.extra_id' => ['nullable', 'exists:extras,id'],
            'gorjeta' => ['nullable', 'numeric', 'min:0'],
            'desconto' => ['nullable', 'numeric', 'min:0'],
            'valor_pago' => ['nullable', 'numeric', 'min:0'],
        ]);

        $calendarEvent = CalendarEvent::query()
            ->forStore(current_store_id())
            ->findOrFail((int) $validated['event_id']);
        if (($calendarEvent->event_type ?? '') !== CalendarEvent::TYPE_MARCACAO) {
            return response()->json(['error' => 'Apenas marcações podem ir a checkout.'], 422);
        }
        if ($calendarEvent->isMarcacaoStatusLocked()) {
            return response()->json(['error' => 'Esta marcação não pode ser paga.'], 422);
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
        $descontoDocInput = isset($validated['desconto']) ? max(0, (float) $validated['desconto']) : 0;
        $alreadyPaidSales = (float) Sale::query()
            ->where('calendar_event_id', $calendarEvent->id)
            ->where('status', '!=', Sale::STATUS_ANULADO)
            ->sum(DB::raw('COALESCE(valor_pago, total)'));
        $bookingPaid = (float) Booking::query()
            ->where('calendar_event_id', $calendarEvent->id)
            ->where('payment_status', Booking::PAYMENT_PAID)
            ->orderByDesc('id')
            ->value('paid_amount');
        $alreadyPaid = round(max($alreadyPaidSales, $bookingPaid, 0.0), 2);
        $descontoDoc = max(0, $descontoDocInput);
        $maxDocDisc = $subtotalItens + $gorjeta;
        $descontoDoc = min($descontoDoc, $maxDocDisc);
        $baseAposDesconto = round(max(0, $subtotalItens + $gorjeta - $descontoDoc), 2);
        $total = round(max(0, $baseAposDesconto - $alreadyPaid), 2);
        if ($total <= 0.00001) {
            return response()->json(['error' => 'Esta marcação já foi faturada.'], 422);
        }
        $valorPago = isset($validated['valor_pago']) ? max(0, (float) $validated['valor_pago']) : $total;
        $valorPago = min($valorPago, $total);

        $now = now();
        $storeId = (int) $calendarEvent->store_id;
        $numeroFatura = Sale::nextNumeroFatura((int) $now->format('Y'), (int) $now->format('m'), $storeId);

        try {
            DB::beginTransaction();

            $sale = Sale::create([
                'store_id' => $storeId,
                'calendar_event_id' => $calendarEvent->id,
                'client_id' => $calendarEvent->client_id,
                'numero_fatura' => $numeroFatura,
                'data_emissao' => $now->toDateString(),
                'total' => $total,
                'gorjeta' => $gorjeta > 0 ? round($gorjeta, 2) : null,
                'desconto' => $descontoDoc > 0 ? round($descontoDoc, 2) : null,
                'valor_pago' => round($valorPago, 2),
                'iva_total' => null,
                'payment_method' => $validated['payment_method'],
                'scope' => Sale::SCOPE_CAIXA_LIQUIDACAO,
                'status' => Sale::STATUS_PAGO,
            ]);

            foreach ($validated['items'] as $idx => $row) {
                $qty = (int) $row['quantidade'];
                $preco = (float) $row['preco_unitario'];
                $bruto = round($qty * $preco, 2);
                $descLinha = isset($row['desconto']) ? (float) $row['desconto'] : 0;
                $descLinha = min(max(0, $descLinha), $bruto);
                $subtotal = round($bruto - $descLinha, 2);
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'tipo' => $row['tipo'],
                    'calendar_event_service_id' => $row['calendar_event_service_id'] ?? null,
                    'service_id' => $row['service_id'] ?? null,
                    'extra_id' => $row['extra_id'] ?? null,
                    'descricao' => $row['descricao'],
                    'quantidade' => $qty,
                    'preco_unitario' => $preco,
                    'subtotal' => $subtotal,
                    'desconto' => $descLinha > 0 ? round($descLinha, 2) : null,
                    'sort_order' => $idx,
                ]);
            }

            // Marcar evento como completo
            $calendarEvent->update(['status' => CalendarEvent::STATUS_COMPLETO]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return response()->json(['error' => 'Erro ao gravar a venda.', 'message' => $e->getMessage()], 500);
        }

        $pdfUrl = route('sales.pdf', $sale);
        $this->syncSaleWithVendus($sale);

        return response()->json([
            'success' => true,
            'sale_id' => $sale->id,
            'numero_fatura' => $sale->numero_fatura,
            'pdf_url' => $pdfUrl,
        ]);
    }

    private function syncSaleWithVendus(Sale $sale): void
    {
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
        $validated = $request->validate([
            'event_id' => ['required', 'exists:calendar_events,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.quantidade' => ['required', 'integer', 'min:1'],
            'items.*.preco_unitario' => ['required', 'numeric', 'min:0'],
            'items.*.desconto' => ['nullable', 'numeric', 'min:0'],
            'gorjeta' => ['nullable', 'numeric', 'min:0'],
            'mbway_phone' => ['nullable', 'string', 'max:40'],
        ]);

        $calendarEvent = CalendarEvent::query()
            ->forStore(current_store_id())
            ->with(['client'])
            ->findOrFail((int) $validated['event_id']);
        if (($calendarEvent->event_type ?? '') !== CalendarEvent::TYPE_MARCACAO) {
            return response()->json(['error' => 'Apenas marcações podem ir a checkout.'], 422);
        }
        if ($calendarEvent->isMarcacaoStatusLocked()) {
            return response()->json(['error' => 'Esta marcação não pode ser paga.'], 422);
        }

        $subtotalItens = $this->checkoutSubtotalFromItems($validated['items']);
        $gorjeta = isset($validated['gorjeta']) ? max(0, (float) $validated['gorjeta']) : 0.0;
        $alreadyPaidSales = (float) Sale::query()
            ->where('calendar_event_id', $calendarEvent->id)
            ->where('status', '!=', Sale::STATUS_ANULADO)
            ->sum(DB::raw('COALESCE(valor_pago, total)'));
        $bookingPaid = (float) Booking::query()
            ->where('calendar_event_id', $calendarEvent->id)
            ->where('payment_status', Booking::PAYMENT_PAID)
            ->orderByDesc('id')
            ->value('paid_amount');
        $alreadyPaid = round(max($alreadyPaidSales, $bookingPaid, 0.0), 2);
        $servicesDue = max(0.0, round($subtotalItens - $alreadyPaid, 2));
        $amountDue = round($servicesDue + $gorjeta, 2);
        if ($amountDue <= 0) {
            return response()->json(['error' => 'Não existe valor em falta para cobrar por MB WAY.'], 422);
        }

        $client = $calendarEvent->client;
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
                    'agenda_event_id' => (string) $calendarEvent->id,
                ],
            ]);
        } catch (ApiErrorException $e) {
            Log::warning('Stripe MB WAY PaymentIntent::create falhou no checkout da agenda.', [
                'event_id' => $calendarEvent->id,
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
            'message' => 'Pedido MB WAY enviado para o cliente.',
        ]);
    }

    /**
     * POST agenda/checkout/mbway/finalize – após confirmação Stripe, cria a venda.
     */
    public function finalizeMbway(Request $request)
    {
        $validated = $request->validate([
            'payment_intent_id' => ['required', 'string', 'max:255'],
            'event_id' => ['required', 'exists:calendar_events,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.tipo' => ['required', 'in:servico,extra'],
            'items.*.descricao' => ['required', 'string', 'max:255'],
            'items.*.quantidade' => ['required', 'integer', 'min:1'],
            'items.*.preco_unitario' => ['required', 'numeric', 'min:0'],
            'items.*.subtotal' => ['nullable', 'numeric', 'min:0'],
            'items.*.desconto' => ['nullable', 'numeric', 'min:0'],
            'items.*.calendar_event_service_id' => ['nullable', 'exists:calendar_event_services,id'],
            'items.*.service_id' => ['nullable', 'exists:services,id'],
            'items.*.extra_id' => ['nullable', 'exists:extras,id'],
            'gorjeta' => ['nullable', 'numeric', 'min:0'],
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

    private function checkoutSubtotalFromEvent(CalendarEvent $calendarEvent): float
    {
        $subtotal = 0.0;
        foreach ($calendarEvent->eventServiceItems as $esi) {
            $subtotal += (float) $esi->price;
            foreach ($esi->extras as $ex) {
                $subtotal += (float) ($ex->price ?? $ex->extra?->price ?? 0);
            }
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
        if ((int) $sale->store_id !== (int) current_store_id()) {
            abort(404);
        }

        if (! $sale->vendus_document_id) {
            return response()->json(['error' => 'Venda ainda sem documento Vendus sincronizado.'], 422);
        }

        $baseUrl = rtrim((string) config('services.vendus.base_url'), '/');
        $apiKey = (string) config('services.vendus.api_key');
        $authMode = strtolower((string) config('services.vendus.auth_mode', 'basic'));
        $vendusMode = (string) config('services.vendus.mode', 'normal');
        $documentId = (int) $sale->vendus_document_id;

        if ($baseUrl === '' || $apiKey === '') {
            return response()->json(['error' => 'Configuração da Vendus incompleta.'], 500);
        }

        $request = Http::accept('application/pdf,application/json')
            ->timeout(25);

        $candidatePaths = [
            "/documents/{$documentId}.pdf?mode=".rawurlencode($vendusMode).'&download=1',
            "/documents/{$documentId}.pdf?mode=".rawurlencode($vendusMode),
            "/documents/{$documentId}/?mode=".rawurlencode($vendusMode).'&output=pdf',
            "/documents/{$documentId}/?mode=".rawurlencode($vendusMode).'&output=pdf_url',
            "/documents/{$documentId}/?mode=".rawurlencode($vendusMode).'&output=auto',
            "/documents/{$documentId}/?mode=".rawurlencode($vendusMode),
        ];

        foreach ($candidatePaths as $path) {
            $url = $baseUrl.$path;
            $response = $this->vendusGetWithAuth($request, $url, $apiKey, $authMode);
            if (! $response->successful()) {
                continue;
            }

            $contentType = strtolower((string) $response->header('Content-Type', ''));
            if (str_contains($contentType, 'application/pdf')) {
                return response($response->body(), 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="vendus-document-'.$documentId.'.pdf"',
                ]);
            }

            $pdfUrl = $this->extractPdfUrlFromVendusPayload($response->json());
            if ($pdfUrl !== null) {
                $binary = $this->fetchVendusPdfBinary($request, $pdfUrl, $apiKey, $authMode, $baseUrl);
                if ($binary !== null) {
                    return response($binary, 200, [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="vendus-document-'.$documentId.'.pdf"',
                    ]);
                }
            }
        }

        return response()->json([
            'error' => 'Nao foi possivel obter o PDF oficial da Vendus para este documento.',
        ], 502);
    }

    /**
     * POST sales/{sale}/revert – anular a venda e desbloquear a marcação para edição.
     */
    public function revert(Request $request, Sale $sale)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
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
        $salesToRevert = Sale::query()
            ->where('calendar_event_id', $calendarEvent->id)
            ->where('status', '!=', Sale::STATUS_ANULADO)
            ->orderBy('id')
            ->get();

        if ($salesToRevert->isEmpty()) {
            return response()->json(['error' => 'Não existem vendas ativas para anular nesta marcação.'], 422);
        }

        $creditNotes = [];
        foreach ($salesToRevert as $candidateSale) {
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

            $creditNotes[] = [
                'sale_id' => $candidateSale->id,
                'sale_numero_fatura' => $candidateSale->numero_fatura,
                'vendus_document_id' => $candidateSale->vendus_document_id,
                'vendus_credit_note_id' => $cnResult['credit_note_id'] ?? null,
            ];
        }

        try {
            DB::beginTransaction();
            foreach ($salesToRevert as $candidateSale) {
                $candidateSale->update(['status' => Sale::STATUS_ANULADO]);
            }
            $calendarEvent->update([
                'status' => CalendarEvent::STATUS_TERMINADO,
                'cancellation_type' => null,
                'cancellation_reason' => null,
            ]);
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
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Venda(s) anulada(s). A marcação ficou em estado Terminado.',
            'event_id' => $calendarEvent->id,
            'reverted_sales_count' => $salesToRevert->count(),
        ]);
    }

    private function vendusGetWithAuth($request, string $url, string $apiKey, string $authMode): \Illuminate\Http\Client\Response
    {
        return match ($authMode) {
            'bearer' => $request->withToken($apiKey)->get($url),
            'query' => $request->get($url, ['api_key' => $apiKey]),
            default => $request->withBasicAuth($apiKey, '')->get($url),
        };
    }

    private function extractPdfUrlFromVendusPayload(mixed $payload): ?string
    {
        if (! is_array($payload)) {
            return null;
        }
        $urls = $this->extractHttpUrlsRecursively($payload);
        foreach ($urls as $url) {
            $u = strtolower($url);
            if (str_contains($u, '.pdf') || str_contains($u, '/pdf') || str_contains($u, 'download') || str_contains($u, 'print')) {
                return $url;
            }
        }

        return $urls[0] ?? null;
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $payload
     * @return list<string>
     */
    private function extractHttpUrlsRecursively(array $payload): array
    {
        $urls = [];
        array_walk_recursive($payload, function (mixed $value) use (&$urls): void {
            if (! is_string($value)) {
                return;
            }
            if (! str_starts_with($value, 'http')) {
                return;
            }
            if (! in_array($value, $urls, true)) {
                $urls[] = $value;
            }
        });

        return $urls;
    }

    private function fetchVendusPdfBinary($request, string $pdfUrl, string $apiKey, string $authMode, string $baseUrl): ?string
    {
        $candidates = [$pdfUrl];
        if (str_starts_with($pdfUrl, '/')) {
            $candidates[] = rtrim($baseUrl, '/').$pdfUrl;
        }

        foreach ($candidates as $url) {
            $plain = $request->get($url);
            if ($plain->successful() && str_contains(strtolower((string) $plain->header('Content-Type', '')), 'application/pdf')) {
                return $plain->body();
            }

            $auth = $this->vendusGetWithAuth($request, $url, $apiKey, $authMode);
            if ($auth->successful() && str_contains(strtolower((string) $auth->header('Content-Type', '')), 'application/pdf')) {
                return $auth->body();
            }
        }

        return null;
    }
}
