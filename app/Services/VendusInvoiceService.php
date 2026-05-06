<?php

namespace App\Services;

use App\Models\Sale;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

final class VendusInvoiceService
{
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

        $sale->loadMissing(['items', 'client']);

        $baseUrl = rtrim((string) config('services.vendus.base_url'), '/');
        $endpoint = trim((string) config('services.vendus.documents_endpoint', '/documents/'));
        $url = $baseUrl.'/'.ltrim($endpoint, '/');
        $apiKey = (string) config('services.vendus.api_key');
        $authMode = strtolower((string) config('services.vendus.auth_mode', 'basic'));
        $categoryId = $this->resolveCategoryIdForSale($sale);
        $payload = $this->buildPayload($sale, $categoryId);

        $request = Http::acceptJson()
            ->asForm()
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

    private function buildPayload(Sale $sale, ?int $categoryId): array
    {
        $items = [];
        foreach ($sale->items as $item) {
            $line = [
                'reference' => $this->buildReusableReference($item),
                'title' => (string) $item->descricao,
                'qty' => (float) $item->quantidade,
                'gross_price' => (float) $item->preco_unitario,
                'type_id' => 'S',
                'tax_id' => (string) config('services.vendus.tax_id', 'NOR'),
            ];
            if ($categoryId !== null && $categoryId > 0) {
                $line['category_id'] = $categoryId;
            }

            if ($item->desconto !== null && (float) $item->desconto > 0) {
                $line['discount_amount'] = (float) $item->desconto;
            }

            $items[] = $line;
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

        $payload = [
            'type' => (string) config('services.vendus.document_type', 'FT'),
            'date' => optional($sale->data_emissao)->format('Y-m-d') ?? now()->toDateString(),
            'external_reference' => 'SALE-'.$sale->id,
            'notes' => 'Origem: agenda / venda #'.$sale->id,
            'client' => $clientData,
            'items' => $items,
            'mode' => (string) config('services.vendus.mode', 'normal'),
        ];

        $registerId = (int) config('services.vendus.register_id', 0);
        if ($registerId > 0) {
            $payload['register_id'] = $registerId;
        }

        if ($sale->desconto !== null && (float) $sale->desconto > 0) {
            $payload['discount_amount'] = (float) $sale->desconto;
        }

        return $payload;
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
}
