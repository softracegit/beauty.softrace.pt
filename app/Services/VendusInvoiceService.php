<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\CalendarEventService;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class VendusInvoiceService
{
    public function __construct(
        private readonly VendusPaymentMethodResolver $vendusPaymentMethods,
    ) {}

    /**
     * @return array{ok: bool, status: int, message: string, document_id: int|null}
     */
    public function syncSale(Sale $sale): array
    {
        if ((bool) config('services.vendus.simulate', false)) {
            return [
                'ok' => true,
                'status' => 200,
                'message' => 'Simulacao Vendus ativa: documento nao enviado para a API.',
                'document_id' => null,
            ];
        }

        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'status' => 0,
                'message' => 'Integracao Vendus nao configurada.',
                'document_id' => null,
            ];
        }

        if ($sale->vendus_document_id !== null) {
            return [
                'ok' => true,
                'status' => 200,
                'message' => 'Venda ja sincronizada na Vendus.',
                'document_id' => (int) $sale->vendus_document_id,
            ];
        }

        $sale->loadMissing([
            'items.service',
            'items.extra',
            'items.calendarEventService.service',
            'calendarEvent.eventServiceItems.service',
            'client',
        ]);

        $baseUrl = rtrim((string) config('services.vendus.base_url'), '/');
        $endpoint = trim((string) config('services.vendus.documents_endpoint', '/documents/'));
        $url = $baseUrl.'/'.ltrim($endpoint, '/');
        $apiKey = (string) config('services.vendus.api_key');
        $authMode = strtolower((string) config('services.vendus.auth_mode', 'basic'));
        $categoryId = $this->resolveCategoryIdForSale($sale);
        $payload = $this->buildPayload($sale, $categoryId);

        // A API Vendus (POST /documents/) espera JSON, não form-urlencoded; caso contrário o `type` pode ser ignorado (fica FT).
        $request = Http::acceptJson()
            ->asJson()
            ->timeout(30);

        Log::info('vendus_invoice_request', [
            'sale_id' => $sale->id,
            'url' => $url,
            'auth_mode' => $authMode,
            'payload' => $this->sanitizePayloadForLog($payload),
        ]);

        $response = $this->requestWithAuth($request, $url, $apiKey, $authMode, $payload);

        Log::info('vendus_invoice_response', [
            'sale_id' => $sale->id,
            'status' => $response->status(),
            'body' => mb_strimwidth(trim($response->body()), 0, 2000, '...'),
        ]);

        if ($response->successful()) {
            $json = $response->json();
            $documentId = isset($json['id']) ? (int) $json['id'] : null;

            return [
                'ok' => true,
                'status' => $response->status(),
                'message' => 'Documento criado na Vendus.',
                'document_id' => $documentId,
            ];
        }

        return [
            'ok' => false,
            'status' => $response->status(),
            'message' => $this->extractErrorMessage($response),
            'document_id' => null,
        ];
    }

    /**
     * @return array{ok: bool, status: int, message: string, credit_note_id: int|null}
     */
    public function createCreditNoteForSale(Sale $sale, string $reason): array
    {
        if ((bool) config('services.vendus.simulate', false)) {
            return [
                'ok' => true,
                'status' => 200,
                'message' => 'Simulacao Vendus ativa: nota de credito nao enviada para a API.',
                'credit_note_id' => null,
            ];
        }

        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'status' => 0,
                'message' => 'Integracao Vendus nao configurada.',
                'credit_note_id' => null,
            ];
        }

        // Reserva online: não gerar NC por integração (evita acidentes no anulamento em lote; reembolso = processo manual na Vendus).
        if (($sale->scope ?? null) === Sale::SCOPE_BOOKING_RESERVA) {
            Log::warning('vendus_credit_note_blocked_booking_reserva', ['sale_id' => $sale->id]);

            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Nota de crédito para fatura de reserva online não é gerada por esta integração. Use a Vendus ou um processo manual acordado.',
                'credit_note_id' => null,
            ];
        }

        $documentId = (int) ($sale->vendus_document_id ?? 0);
        if ($documentId <= 0) {
            return [
                'ok' => true,
                'status' => 200,
                'message' => 'Venda sem documento Vendus associado.',
                'credit_note_id' => null,
            ];
        }

        $sale->loadMissing([
            'items.service',
            'items.extra',
            'items.calendarEventService.service',
            'calendarEvent.eventServiceItems.service',
            'client',
        ]);

        $baseUrl = rtrim((string) config('services.vendus.base_url'), '/');
        $apiKey = (string) config('services.vendus.api_key');
        $authMode = strtolower((string) config('services.vendus.auth_mode', 'basic'));
        $mode = (string) config('services.vendus.mode', 'normal');

        $request = Http::acceptJson()
            ->asJson()
            ->timeout(30);

        $note = trim('Nota de credito automatica da venda #'.$sale->id.' (doc original #'.$documentId.')'.($reason !== '' ? ' — '.$reason : ''));
        $date = optional($sale->data_emissao)->format('Y-m-d') ?? now()->toDateString();
        $originalDocument = $this->fetchOriginalDocumentDetail($documentId, $baseUrl, $apiKey, $authMode);
        $originalDocumentNumber = $this->extractOriginalDocumentNumber($sale, $originalDocument);
        $minimalPayload = [
            'date' => $date,
            'mode' => $mode,
            'notes' => $note,
        ];

        $endpointCandidates = [
            "/documents/{$documentId}/credit-notes/",
            "/documents/{$documentId}/credit-note/",
            "/documents/{$documentId}/creditnotes/",
        ];

        foreach ($endpointCandidates as $path) {
            $url = $baseUrl.$path;
            $response = $this->requestWithAuth($request, $url, $apiKey, $authMode, $minimalPayload);
            Log::info('vendus_credit_note_response', [
                'sale_id' => $sale->id,
                'vendus_document_id' => $documentId,
                'url' => $url,
                'status' => $response->status(),
                'body' => mb_strimwidth(trim($response->body()), 0, 1500, '...'),
            ]);
            if ($response->successful()) {
                return [
                    'ok' => true,
                    'status' => $response->status(),
                    'message' => 'Nota de credito criada na Vendus.',
                    'credit_note_id' => $this->extractDocumentId($response->json()),
                ];
            }
        }

        [$items, $missingItemRefs] = $this->buildCreditNoteItemsFromOriginalDocument($documentId, $originalDocumentNumber, $originalDocument, $baseUrl, $apiKey, $authMode);
        if ($missingItemRefs !== []) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Nao foi possivel resolver o ID do(s) produto(s) na Vendus para NC: '.implode(', ', $missingItemRefs),
                'credit_note_id' => null,
            ];
        }
        if ($items === []) {
            $taxId = (string) config('services.vendus.tax_id', 'NOR');
            $categoryId = $this->resolveCategoryIdForSale($sale);
            [$fallbackItems] = $this->buildDetailedVendusItems($sale, $categoryId, $taxId);
            $items = $fallbackItems;
        }
        $clientData = $this->buildDocumentClientPayloadForVendus($sale);

        $fallbackPayload = [
            'type' => strtoupper((string) config('services.vendus.credit_note_type', 'NC')),
            'date' => $date,
            'mode' => $mode,
            'external_reference' => 'SALE-CN-'.$sale->id,
            'notes' => $note,
            'items' => $items,
            'related_document_id' => $documentId,
            'invoices' => [
                [
                    'document_number' => $originalDocumentNumber,
                ],
            ],
        ];
        if ($clientData !== null) {
            $fallbackPayload['client'] = $clientData;
        }

        $documentsEndpoint = trim((string) config('services.vendus.documents_endpoint', '/documents/'));
        $documentsUrl = $baseUrl.'/'.ltrim($documentsEndpoint, '/');
        $fallbackResponse = $this->requestWithAuth($request, $documentsUrl, $apiKey, $authMode, $fallbackPayload);

        Log::info('vendus_credit_note_fallback_response', [
            'sale_id' => $sale->id,
            'vendus_document_id' => $documentId,
            'url' => $documentsUrl,
            'status' => $fallbackResponse->status(),
            'body' => mb_strimwidth(trim($fallbackResponse->body()), 0, 2000, '...'),
        ]);

        if ($fallbackResponse->successful()) {
            return [
                'ok' => true,
                'status' => $fallbackResponse->status(),
                'message' => 'Nota de credito criada na Vendus.',
                'credit_note_id' => $this->extractDocumentId($fallbackResponse->json()),
            ];
        }

        return [
            'ok' => false,
            'status' => $fallbackResponse->status(),
            'message' => $this->extractErrorMessage($fallbackResponse),
            'credit_note_id' => null,
        ];
    }

    private function isConfigured(): bool
    {
        return (string) config('services.vendus.api_key') !== ''
            && (string) config('services.vendus.base_url') !== '';
    }

    private function requestWithAuth($request, string $url, string $apiKey, string $authMode, array $payload): Response
    {
        return match ($authMode) {
            'bearer' => $request->withToken($apiKey)->post($url, $payload),
            'query' => $request->post($url.'?'.http_build_query(['api_key' => $apiKey]), $payload),
            default => $request->withBasicAuth($apiKey, '')->post($url, $payload),
        };
    }

    /**
     * Payload `client` para criar documentos na Vendus, ou null para não enviar o bloco.
     * Sem NIF (consumidor final): a doc. oficial indica que o `client` pode ser omitido se não
     * houver fiscal_id; enviar nome/email/telefone faz a Vendus associar a um cliente existente
     * e repor o NIF. Um objeto só com "Consumidor final" chegou a ser rejeitado pela API em testes.
     */
    private function buildDocumentClientPayloadForVendus(Sale $sale): ?array
    {
        if ((bool) ($sale->issue_without_fiscal_id ?? false)) {
            return null;
        }

        $client = $sale->client;
        $clientData = [
            'name' => (string) ($client?->name ?? 'Cliente'),
            'email' => (string) ($client?->email ?? ''),
            'phone' => (string) ($client?->phone ?? ''),
            'fiscal_id' => (string) ($client?->nif ?? ''),
            'country' => 'PT',
        ];

        if (($clientData['fiscal_id'] ?? '') === '') {
            unset($clientData['fiscal_id']);
        }
        if (($clientData['email'] ?? '') === '') {
            unset($clientData['email']);
        }
        if (($clientData['phone'] ?? '') === '') {
            unset($clientData['phone']);
        }

        return $clientData;
    }

    private function buildPayload(Sale $sale, ?int $categoryId): array
    {
        $taxId = (string) config('services.vendus.tax_id', 'NOR');
        [$items, $itemsNet] = $this->buildDetailedVendusItems($sale, $categoryId, $taxId);

        $headerDisc = ($sale->desconto !== null && (float) $sale->desconto > 0)
            ? round((float) $sale->desconto, 2)
            : 0.0;

        $toPay = round($sale->effectiveAmountPaid(), 2);
        $impliedFromDetail = round($itemsNet - $headerDisc, 2);

        // Valor coberto por fatura de reserva/sinal: o detalhe interno soma o serviço completo mas o cliente paga só o remanescente.
        // Na Vendus enviamos uma linha única com esse valor (sem "desconto" fictício).
        $collapsedToLiquidationLine = $impliedFromDetail > $toPay + 0.02;
        if ($collapsedToLiquidationLine) {
            $items = [
                $this->buildLiquidationVendusLine($sale, $toPay, $categoryId, $taxId),
            ];
            $itemsNet = $toPay;
            $headerDisc = 0.0;
        }

        $clientData = $this->buildDocumentClientPayloadForVendus($sale);

        $docType = strtoupper(trim((string) config('services.vendus.document_type', 'FR')));

        $payload = [
            'type' => $docType,
            'date' => optional($sale->data_emissao)->format('Y-m-d') ?? now()->toDateString(),
            'external_reference' => 'SALE-'.$sale->id,
            'notes' => 'Origem: agenda / venda #'.$sale->id,
            'items' => $items,
            'mode' => (string) config('services.vendus.mode', 'normal'),
        ];
        if ($clientData !== null) {
            $payload['client'] = $clientData;
        }

        $registerId = (int) config('services.vendus.register_id', 0);
        if ($registerId > 0) {
            $payload['register_id'] = $registerId;
        }

        // FR: total do documento = payments.amount. Se ainda houver desvio (ex. arredondamentos), desconto de cabeçalho real.
        if ($docType === 'FR' && ! $collapsedToLiquidationLine) {
            $impliedDocTotal = round($itemsNet - $headerDisc, 2);
            $extraDiscount = round(max(0.0, $impliedDocTotal - $toPay), 2);
            $totalDisc = round($headerDisc + $extraDiscount, 2);
            if ($totalDisc > 0.00001) {
                $payload['discount_amount'] = $totalDisc;
            }
            if ($toPay > $impliedDocTotal + 0.02) {
                Log::warning('vendus_fr_payment_exceeds_line_total', [
                    'sale_id' => $sale->id,
                    'items_net' => $itemsNet,
                    'header_discount' => $headerDisc,
                    'implied_doc_total' => $impliedDocTotal,
                    'to_pay' => $toPay,
                ]);
            }
        } elseif ($docType !== 'FR' && $headerDisc > 0.00001) {
            $payload['discount_amount'] = $headerDisc;
        }

        if ($docType === 'FR') {
            $paymentMethodId = $this->vendusPaymentMethods->resolvePaymentMethodIdForSale($sale);
            if ($paymentMethodId !== null && $paymentMethodId !== '') {
                $payload['payments'] = [
                    [
                        'id' => $paymentMethodId,
                        'amount' => round($sale->effectiveAmountPaid(), 2),
                    ],
                ];
            }
        }

        return $payload;
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: float}
     */
    private function buildDetailedVendusItems(Sale $sale, ?int $categoryId, string $taxId): array
    {
        $items = [];
        $itemsNet = 0.0;
        foreach ($sale->items as $item) {
            $line = [
                'reference' => $this->buildReusableReference($item),
                'title' => $this->vendusLineTitle($item, $sale),
                'qty' => (float) $item->quantidade,
                'gross_price' => (float) $item->preco_unitario,
                'type_id' => 'S',
                'tax_id' => $taxId,
            ];
            if ($categoryId !== null && $categoryId > 0) {
                $line['category_id'] = $categoryId;
            }

            if ($item->desconto !== null && (float) $item->desconto > 0) {
                $line['discount_amount'] = (float) $item->desconto;
            }

            $lineText = $this->vendusLineContextText($sale);
            if ($lineText !== null && $lineText !== '') {
                $line['text'] = $lineText;
            }

            $items[] = $line;

            $brutoLinha = round((float) $item->quantidade * (float) $item->preco_unitario, 2);
            $descLinha = ($item->desconto !== null && (float) $item->desconto > 0)
                ? min((float) $item->desconto, $brutoLinha)
                : 0.0;
            $itemsNet += round($brutoLinha - $descLinha, 2);
        }

        if ($sale->gorjeta !== null && (float) $sale->gorjeta > 0.00001) {
            $g = round((float) $sale->gorjeta, 2);
            $gLine = [
                'reference' => 'GORJETA',
                'title' => 'Gorjeta',
                'qty' => 1.0,
                'gross_price' => $g,
                'type_id' => 'S',
                'tax_id' => $taxId,
            ];
            if ($categoryId !== null && $categoryId > 0) {
                $gLine['category_id'] = $categoryId;
            }
            $items[] = $gLine;
            $itemsNet = round($itemsNet + $g, 2);
        }

        return [$items, round($itemsNet, 2)];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLiquidationVendusLine(Sale $sale, float $amount, ?int $categoryId, string $taxId): array
    {
        $refId = $sale->calendar_event_id ?? $sale->id;
        $line = [
            'reference' => $this->vendusLiquidationReference($sale) ?? 'MARC-CE-'.$refId.'-LOJA',
            'title' => $this->vendusLiquidationTitle($sale),
            'qty' => 1.0,
            'gross_price' => round($amount, 2),
            'type_id' => 'S',
            'tax_id' => $taxId,
        ];
        if ($categoryId !== null && $categoryId > 0) {
            $line['category_id'] = $categoryId;
        }

        $lineText = $this->vendusLineContextText($sale);
        if ($lineText !== null && $lineText !== '') {
            $line['text'] = $lineText;
        }

        return $line;
    }

    /**
     * Texto livre por linha na Vendus (campo `text` na API): contexto da venda sem alterar o produto (`title` / `reference`).
     */
    private function vendusLineContextText(Sale $sale): ?string
    {
        return match ($sale->scope) {
            Sale::SCOPE_BOOKING_RESERVA => 'Valor da reserva online',
            Sale::SCOPE_CAIXA_LIQUIDACAO => 'Pagamento final em loja',
            default => null,
        };
    }

    /**
     * Título do artigo na Vendus: só nome do serviço (ou extra), nunca a descrição longa da linha —
     * senão a API cria produtos duplicados.
     */
    private function vendusLineTitle(SaleItem $item, Sale $sale): string
    {
        $item->loadMissing(['service', 'extra', 'calendarEventService.service']);

        if (($item->tipo ?? null) === SaleItem::TIPO_EXTRA || ($item->extra_id ?? null)) {
            $item->loadMissing('extra');
            $n = trim((string) ($item->extra?->name ?? ''));
            if ($n !== '') {
                return $n;
            }
        }

        $ces = $item->calendarEventService;
        if ($ces instanceof CalendarEventService) {
            $ces->loadMissing('service');
            $opt = trim((string) ($ces->option_name ?? ''));
            if ($opt !== '') {
                return $opt;
            }
            $n = trim((string) ($ces->service?->name ?? ''));
            if ($n !== '') {
                return $n;
            }
        }

        if (! empty($item->service_id)) {
            $item->loadMissing('service');
            $n = trim((string) ($item->service?->name ?? ''));
            if ($n !== '') {
                return $n;
            }
        }

        $labels = $this->vendusServiceLabelsFromCalendarEvent($sale->calendarEvent);
        if ($labels !== []) {
            return $labels[0];
        }

        return $this->vendusTitleFallbackFromDescricao((string) ($item->descricao ?? ''));
    }

    /**
     * Uma linha de «saldo em loja»: mesmo produto que o 1.º serviço da marcação quando possível.
     */
    private function vendusLiquidationTitle(Sale $sale): string
    {
        $labels = $this->vendusServiceLabelsFromCalendarEvent($sale->calendarEvent);
        if ($labels !== []) {
            return $labels[0];
        }

        foreach ($sale->items as $item) {
            $t = $this->vendusLineTitle($item, $sale);
            if ($t !== '' && ! in_array(mb_strtolower($t), ['serviço', 'serviços'], true)) {
                return $t;
            }
        }

        return 'Serviço';
    }

    private function vendusLiquidationReference(Sale $sale): ?string
    {
        $optionId = $this->primaryServiceOptionIdFromCalendarOrItems($sale);
        if ($optionId !== null) {
            return 'SRV-'.$optionId;
        }

        $sid = $this->primaryServiceIdFromCalendarOrItems($sale);

        return $sid !== null ? 'SRV-'.$sid : null;
    }

    private function primaryServiceOptionIdFromCalendarOrItems(Sale $sale): ?int
    {
        $sale->loadMissing(['calendarEvent.eventServiceItems', 'items.calendarEventService']);
        $firstRow = $sale->calendarEvent?->eventServiceItems->first();
        if ($firstRow && (int) ($firstRow->service_option_id ?? 0) > 0) {
            return (int) $firstRow->service_option_id;
        }
        foreach ($sale->items as $item) {
            $optId = (int) ($item->calendarEventService?->service_option_id ?? 0);
            if ($optId > 0) {
                return $optId;
            }
        }

        return null;
    }

    private function primaryServiceIdFromCalendarOrItems(Sale $sale): ?int
    {
        $sale->loadMissing(['calendarEvent.eventServiceItems', 'items']);
        $firstRow = $sale->calendarEvent?->eventServiceItems->first();
        if ($firstRow && (int) ($firstRow->service_id ?? 0) > 0) {
            return (int) $firstRow->service_id;
        }
        foreach ($sale->items as $item) {
            if (! empty($item->service_id)) {
                return (int) $item->service_id;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function vendusServiceLabelsFromCalendarEvent(?CalendarEvent $event): array
    {
        if ($event === null) {
            return [];
        }
        $event->loadMissing('eventServiceItems.service');

        return $event->eventServiceItems
            ->map(function ($row) {
                $opt = trim((string) ($row->option_name ?? ''));
                if ($opt !== '') {
                    return $opt;
                }

                return trim((string) ($row->service?->name ?? ''));
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function vendusTitleFallbackFromDescricao(string $desc): string
    {
        $desc = trim($desc);
        if ($desc === '') {
            return 'Serviço';
        }
        if (preg_match('/^(.+?)\s*—/u', $desc, $m)) {
            return trim($m[1]);
        }

        return $desc;
    }

    private function resolveCategoryIdForSale(Sale $sale): ?int
    {
        $idFromEnv = (int) config('services.vendus.category_id', 0);
        if ($idFromEnv > 0) {
            return $idFromEnv;
        }

        $title = trim((string) config('services.vendus.category_title', ''));
        if ($title === '') {
            return null;
        }

        $baseUrl = rtrim((string) config('services.vendus.base_url'), '/');
        $apiKey = (string) config('services.vendus.api_key');
        $authMode = strtolower((string) config('services.vendus.auth_mode', 'basic'));

        if ($baseUrl === '' || $apiKey === '') {
            return null;
        }

        $cacheKey = 'vendus_category_id:'.sha1($baseUrl.'|'.$title);

        return Cache::remember($cacheKey, 3600, function () use ($baseUrl, $apiKey, $authMode, $title) {
            $url = $baseUrl.'/products/categories/';

            $request = Http::acceptJson()->timeout(30);
            $response = match ($authMode) {
                'bearer' => $request->withToken($apiKey)->get($url, ['title' => $title, 'status' => 'on']),
                'query' => $request->get($url, array_merge(['title' => $title, 'status' => 'on'], ['api_key' => $apiKey])),
                default => $request->withBasicAuth($apiKey, '')->get($url, ['title' => $title, 'status' => 'on']),
            };

            if (! $response->successful()) {
                Log::warning('vendus_category_resolve_failed', [
                    'status' => $response->status(),
                    'body' => mb_strimwidth(trim($response->body()), 0, 1000, '...'),
                ]);

                return null;
            }

            $json = $response->json();

            // A resposta pode vir como lista direta ou como { data: [...] }.
            $rows = null;
            if (is_array($json) && array_key_exists('data', $json) && is_array($json['data'])) {
                $rows = $json['data'];
            } elseif (is_array($json) && array_is_list($json)) {
                $rows = $json;
            }

            if (! is_array($rows) || $rows === []) {
                // fallback: se vier como array associativo único com id/title.
                if (is_array($json) && isset($json['id']) && is_numeric($json['id'])) {
                    return (int) $json['id'];
                }

                return null;
            }

            foreach ($rows as $row) {
                if (is_array($row) && isset($row['id']) && is_numeric($row['id'])) {
                    return (int) $row['id'];
                }
            }

            return null;
        });
    }

    private function buildReusableReference($item): string
    {
        $item->loadMissing(['calendarEventService']);
        $optionId = (int) ($item->calendarEventService?->service_option_id ?? 0);
        if ($optionId > 0) {
            return 'SRV-'.$optionId;
        }

        if (($item->tipo ?? null) === 'servico' && ! empty($item->service_id)) {
            return 'SRV-'.$item->service_id;
        }
        if (($item->tipo ?? null) === 'extra' && ! empty($item->extra_id)) {
            return 'EXT-'.$item->extra_id;
        }

        // Fallback estavel por descricao/tipo, para evitar criar produto a cada venda.
        $base = strtoupper((string) ($item->tipo ?? 'ITEM'));
        $hash = strtoupper(substr(sha1(mb_strtolower(trim((string) ($item->descricao ?? 'item')))), 0, 10));

        return $base.'-'.$hash;
    }

    private function extractErrorMessage(Response $response): string
    {
        $json = $response->json();
        if (is_array($json)) {
            if (! empty($json['errors']) && is_array($json['errors'])) {
                $flattened = [];
                array_walk_recursive($json['errors'], function ($value) use (&$flattened): void {
                    if (is_scalar($value)) {
                        $flattened[] = (string) $value;
                    }
                });
                if ($flattened !== []) {
                    return implode(' | ', $flattened);
                }
            }
            foreach (['error', 'message', 'detail'] as $key) {
                if (! empty($json[$key]) && is_string($json[$key])) {
                    return (string) $json[$key];
                }
            }
        }
        $body = trim($response->body());
        if ($body !== '') {
            return mb_strimwidth($body, 0, 800, '...');
        }

        return 'Falha ao criar documento na Vendus (HTTP '.$response->status().').';
    }

    private function sanitizePayloadForLog(array $payload): array
    {
        $copy = $payload;
        if (isset($copy['client']) && is_array($copy['client'])) {
            if (! empty($copy['client']['email'])) {
                $copy['client']['email'] = $this->mask((string) $copy['client']['email']);
            }
            if (! empty($copy['client']['phone'])) {
                $copy['client']['phone'] = $this->mask((string) $copy['client']['phone']);
            }
            if (! empty($copy['client']['fiscal_id'])) {
                $copy['client']['fiscal_id'] = $this->mask((string) $copy['client']['fiscal_id']);
            }
        }

        return $copy;
    }

    private function mask(string $value): string
    {
        $len = mb_strlen($value);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        return mb_substr($value, 0, 2).str_repeat('*', max(0, $len - 4)).mb_substr($value, -2);
    }

    private function extractDocumentId(mixed $payload): ?int
    {
        if (! is_array($payload)) {
            return null;
        }

        if (isset($payload['id']) && is_numeric($payload['id'])) {
            return (int) $payload['id'];
        }
        if (isset($payload['document']) && is_array($payload['document']) && isset($payload['document']['id']) && is_numeric($payload['document']['id'])) {
            return (int) $payload['document']['id'];
        }
        if (isset($payload['data']) && is_array($payload['data']) && isset($payload['data']['id']) && is_numeric($payload['data']['id'])) {
            return (int) $payload['data']['id'];
        }

        return null;
    }

    private function resolveOriginalDocumentNumber(Sale $sale, string $baseUrl, string $apiKey, string $authMode): string
    {
        $documentId = (int) ($sale->vendus_document_id ?? 0);
        $doc = $this->fetchOriginalDocumentDetail($documentId, $baseUrl, $apiKey, $authMode);

        return $this->extractOriginalDocumentNumber($sale, $doc);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchOriginalDocumentDetail(int $documentId, string $baseUrl, string $apiKey, string $authMode): ?array
    {
        if ($documentId <= 0) {
            return null;
        }
        try {
            $request = Http::acceptJson()->timeout(20);
            $url = rtrim($baseUrl, '/').'/documents/'.$documentId.'/?mode='.(string) config('services.vendus.mode', 'normal');
            $response = match ($authMode) {
                'bearer' => $request->withToken($apiKey)->get($url),
                'query' => $request->get($url, ['api_key' => $apiKey]),
                default => $request->withBasicAuth($apiKey, '')->get($url),
            };
            if (! $response->successful()) {
                return null;
            }
            $json = $response->json();

            return is_array($json) ? $json : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractOriginalDocumentNumber(Sale $sale, ?array $doc): string
    {
        $fallback = (string) ($sale->numero_fatura ?? '');
        if (! is_array($doc)) {
            return $fallback;
        }
        $candidates = [
            $doc['document_number'] ?? null,
            $doc['number'] ?? null,
            $doc['reference'] ?? null,
            (is_array($doc['data'] ?? null) ? ($doc['data']['document_number'] ?? null) : null),
            (is_array($doc['data'] ?? null) ? ($doc['data']['number'] ?? null) : null),
            (is_array($doc['data'] ?? null) ? ($doc['data']['reference'] ?? null) : null),
        ];
        foreach ($candidates as $value) {
            $v = trim((string) ($value ?? ''));
            if ($v !== '') {
                return $v;
            }
        }

        return $fallback;
    }

    /**
     * @param  array<string, mixed>|null  $doc
     * @return array{0:list<array<string,mixed>>,1:list<string>}
     */
    private function buildCreditNoteItemsFromOriginalDocument(int $documentId, string $documentNumber, ?array $doc, string $baseUrl, string $apiKey, string $authMode): array
    {
        if (! is_array($doc)) {
            return [[], []];
        }
        $rows = $doc['items'] ?? (is_array($doc['data'] ?? null) ? ($doc['data']['items'] ?? null) : null);
        if (! is_array($rows) || $rows === []) {
            return [[], []];
        }

        $items = [];
        $missingRefs = [];
        foreach (array_values($rows) as $idx => $row) {
            if (! is_array($row)) {
                continue;
            }
            $qty = (float) ($row['qty'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $reference = trim((string) ($row['reference'] ?? ''));
            $productId = $this->extractProductIdFromDocumentRow($row);
            if (! $productId && $reference !== '') {
                $productId = $this->resolveVendusProductIdByReference($reference, $baseUrl, $apiKey, $authMode);
            }
            if (! $productId && $reference === '') {
                continue;
            }

            $item = [
                'qty' => $qty,
                'reference_document' => [
                    'document_number' => $documentNumber,
                    'document_row' => $idx + 1,
                    'reference_id' => $documentId,
                ],
            ];

            // Para NC nesta conta, Vendus exige id do produto/item.
            if ($productId) {
                $item['id'] = $productId;
            } else {
                $missingRefs[] = $reference !== '' ? $reference : ('row#'.($idx + 1));

                continue;
            }
            $items[] = $item;
        }

        return [$items, array_values(array_unique($missingRefs))];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function extractProductIdFromDocumentRow(array $row): ?int
    {
        $candidates = [
            $row['product_id'] ?? null,
            $row['item_id'] ?? null,
            $row['article_id'] ?? null,
        ];
        foreach ($candidates as $candidate) {
            if (is_numeric($candidate) && (int) $candidate > 0) {
                return (int) $candidate;
            }
        }

        return null;
    }

    private function resolveVendusProductIdByReference(string $reference, string $baseUrl, string $apiKey, string $authMode): ?int
    {
        $ref = trim($reference);
        if ($ref === '' || $baseUrl === '' || $apiKey === '') {
            return null;
        }
        $cacheKey = 'vendus_product_id_by_reference:'.sha1($baseUrl.'|'.$ref);

        return Cache::remember($cacheKey, 3600, function () use ($ref, $baseUrl, $apiKey, $authMode) {
            try {
                $request = Http::acceptJson()->timeout(20);
                $url = rtrim($baseUrl, '/').'/products/';
                $mode = (string) config('services.vendus.mode', 'normal');
                $queries = [
                    ['reference' => $ref, 'mode' => $mode],
                    ['q' => $ref, 'mode' => $mode],
                    ['q' => $ref],
                    ['reference' => $ref],
                ];

                foreach ($queries as $query) {
                    $response = match ($authMode) {
                        'bearer' => $request->withToken($apiKey)->get($url, $query),
                        'query' => $request->get($url, array_merge($query, ['api_key' => $apiKey])),
                        default => $request->withBasicAuth($apiKey, '')->get($url, $query),
                    };
                    if (! $response->successful()) {
                        continue;
                    }
                    $rows = $this->extractRowsFromVendusCollectionPayload($response->json());
                    foreach ($rows as $row) {
                        if (! is_array($row)) {
                            continue;
                        }
                        $rowRef = trim((string) ($row['reference'] ?? ''));
                        if ($rowRef !== '' && strcasecmp($rowRef, $ref) !== 0) {
                            continue;
                        }
                        if (isset($row['id']) && is_numeric($row['id']) && (int) $row['id'] > 0) {
                            return (int) $row['id'];
                        }
                    }
                }

                // Último recurso: tenta endpoint direto por referência.
                $directUrl = rtrim($baseUrl, '/').'/products/'.rawurlencode($ref).'/';
                $directResponse = match ($authMode) {
                    'bearer' => $request->withToken($apiKey)->get($directUrl, ['mode' => $mode]),
                    'query' => $request->get($directUrl, ['mode' => $mode, 'api_key' => $apiKey]),
                    default => $request->withBasicAuth($apiKey, '')->get($directUrl, ['mode' => $mode]),
                };
                if ($directResponse->successful()) {
                    $rows = $this->extractRowsFromVendusCollectionPayload($directResponse->json());
                    foreach ($rows as $row) {
                        if (! is_array($row)) {
                            continue;
                        }
                        if (isset($row['id']) && is_numeric($row['id']) && (int) $row['id'] > 0) {
                            return (int) $row['id'];
                        }
                    }
                }
            } catch (\Throwable) {
                return null;
            }

            return null;
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractRowsFromVendusCollectionPayload(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }
        if (array_is_list($payload)) {
            return array_values(array_filter($payload, 'is_array'));
        }
        foreach (['data', 'items', 'products', 'results', 'rows'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $rows = $payload[$key];
                if (array_is_list($rows)) {
                    return array_values(array_filter($rows, 'is_array'));
                }
            }
        }
        // Alguns endpoints retornam um único objeto diretamente.
        if (isset($payload['id']) && is_numeric($payload['id'])) {
            return [$payload];
        }

        return [];
    }
}
