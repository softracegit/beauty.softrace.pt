<?php

namespace App\Services;

use App\Models\Sale;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class VendusPaymentMethodResolver
{
    /**
     * Resolve o ID Vendus do meio de pagamento para liquidar uma FR.
     * 1) Override opcional: VENDUS_PAYMENT_METHOD_ID
     * 2) GET /documents/paymentmethods/ + correspondência pelo tipo AT (NU, MBWAY, …) e pela loja.
     */
    public function resolvePaymentMethodIdForSale(Sale $sale): ?string
    {
        $override = trim((string) config('services.vendus.payment_method_id', ''));
        if ($override !== '') {
            return $override;
        }

        if (! $this->isConfigured()) {
            return null;
        }

        $vendusTypes = $this->vendusTypesForSalePayment($sale->payment_method);
        if ($vendusTypes === []) {
            Log::warning('vendus_payment_method_unmapped_sale_method', [
                'sale_id' => $sale->id,
                'payment_method' => $sale->payment_method,
            ]);

            return null;
        }

        $methods = $this->fetchPaymentMethods();
        if ($methods === []) {
            return null;
        }

        $storeId = isset($sale->store_id) && (int) $sale->store_id > 0
            ? (int) $sale->store_id
            : null;

        foreach ($vendusTypes as $vendusType) {
            foreach ($methods as $row) {
                if (! is_array($row) || ! isset($row['id']) || ! is_numeric($row['id'])) {
                    continue;
                }
                if (! $this->isPaymentMethodRowActive($row)) {
                    continue;
                }
                if (! $this->paymentMethodAvailableForStore($row, $storeId)) {
                    continue;
                }
                if (strtoupper((string) ($row['type'] ?? '')) !== $vendusType) {
                    continue;
                }

                return (string) (int) $row['id'];
            }
        }

        Log::warning('vendus_payment_method_not_found_in_vendus', [
            'sale_id' => $sale->id,
            'payment_method' => $sale->payment_method,
            'expected_vendus_types' => $vendusTypes,
        ]);

        return null;
    }

    /**
     * Lista meios de pagamento ativos na conta (para debug / comando Artisan).
     *
     * @return list<array<string, mixed>>
     */
    public function listPaymentMethods(): array
    {
        return $this->fetchPaymentMethods();
    }

    /**
     * @return list<string> códigos de tipo Vendus (ex.: MBWAY, NU), por ordem de preferência
     */
    private function vendusTypesForSalePayment(?string $paymentMethod): array
    {
        return match ($paymentMethod) {
            Sale::PAYMENT_DINHEIRO => ['NU'],
            // Em algumas contas Vendus não existe tipo MBWAY; FR exige pagamento no ato.
            // Fallback para cartões evita erro A001 em documentos FR.
            Sale::PAYMENT_MBWAY => ['MBWAY', 'CC', 'CD', 'NU'],
            Sale::PAYMENT_MULTIBANCO => ['MB', 'CD'],
            Sale::PAYMENT_TRANSFERENCIA => ['TB'],
            Sale::PAYMENT_CARTAO => ['CC', 'CD'],
            Sale::PAYMENT_OUTRO => ['OU'],
            default => [],
        };
    }

    private function isConfigured(): bool
    {
        return (string) config('services.vendus.api_key') !== ''
            && (string) config('services.vendus.base_url') !== '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchPaymentMethods(): array
    {
        $baseUrl = rtrim((string) config('services.vendus.base_url'), '/');
        $apiKey = (string) config('services.vendus.api_key');
        $authMode = strtolower((string) config('services.vendus.auth_mode', 'basic'));

        if ($baseUrl === '' || $apiKey === '') {
            return [];
        }

        $cacheKey = 'vendus_payment_methods:'.sha1($baseUrl.'|'.$apiKey);

        /** @var list<array<string, mixed>> */
        return Cache::remember($cacheKey, 3600, function () use ($baseUrl, $apiKey, $authMode) {
            $url = $baseUrl.'/documents/paymentmethods/';

            $request = Http::acceptJson()->timeout(30);
            $response = match ($authMode) {
                'bearer' => $request->withToken($apiKey)->get($url),
                'query' => $request->get($url, ['api_key' => $apiKey]),
                default => $request->withBasicAuth($apiKey, '')->get($url),
            };

            if (! $response->successful()) {
                Log::warning('vendus_payment_methods_fetch_failed', [
                    'status' => $response->status(),
                    'body' => mb_strimwidth(trim($response->body()), 0, 1000, '...'),
                ]);

                return [];
            }

            $json = $response->json();
            $rows = null;
            if (is_array($json) && array_key_exists('data', $json) && is_array($json['data'])) {
                $rows = $json['data'];
            } elseif (is_array($json) && array_is_list($json)) {
                $rows = $json;
            }

            if (! is_array($rows)) {
                return [];
            }

            /** @var list<array<string, mixed>> $out */
            $out = [];
            foreach ($rows as $row) {
                if (is_array($row)) {
                    $out[] = $row;
                }
            }

            return $out;
        });
    }

    private function isPaymentMethodRowActive(array $row): bool
    {
        $s = strtolower((string) ($row['status'] ?? 'on'));

        return $s !== 'off' && $s !== 'inactive' && $s !== '0';
    }

    private function paymentMethodAvailableForStore(array $row, ?int $storeId): bool
    {
        if ($storeId === null) {
            return true;
        }

        $stores = $row['stores'] ?? null;
        if (! is_array($stores) || $stores === []) {
            return true;
        }

        foreach ($stores as $sid) {
            if (is_array($sid) && isset($sid['id'])) {
                $sid = $sid['id'];
            }
            if ((int) $sid === $storeId) {
                return true;
            }
        }

        return false;
    }
}
