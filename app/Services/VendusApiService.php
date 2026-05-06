<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class VendusApiService
{
    /**
     * @return array{ok: bool, status: int, message: string}
     */
    public function testConnection(): array
    {
        $apiKey = (string) config('services.vendus.api_key');
        $baseUrl = rtrim((string) config('services.vendus.base_url'), '/');
        $authMode = strtolower((string) config('services.vendus.auth_mode', 'bearer'));

        if ($apiKey === '' || $baseUrl === '') {
            return [
                'ok' => false,
                'status' => 0,
                'message' => 'VENDUS_API_KEY e/ou VENDUS_BASE_URL nao estao configurados.',
            ];
        }

        $url = $baseUrl.'/account/';
        $request = Http::acceptJson()->timeout(20);
        $response = $this->requestWithAuth($request, $url, $apiKey, $authMode);

        if ($response->successful()) {
            return [
                'ok' => true,
                'status' => $response->status(),
                'message' => 'Ligacao a API Vendus validada com sucesso.',
            ];
        }

        $message = $this->extractErrorMessage($response);

        return [
            'ok' => false,
            'status' => $response->status(),
            'message' => $message,
        ];
    }

    private function requestWithAuth($request, string $url, string $apiKey, string $authMode): Response
    {
        return match ($authMode) {
            'basic' => $request->withBasicAuth($apiKey, '')->get($url),
            'query' => $request->get($url, ['api_key' => $apiKey]),
            default => $request->withToken($apiKey)->get($url),
        };
    }

    private function extractErrorMessage(Response $response): string
    {
        $json = $response->json();
        if (is_array($json)) {
            foreach (['error', 'message', 'detail'] as $key) {
                if (! empty($json[$key]) && is_string($json[$key])) {
                    return $json[$key];
                }
            }
        }

        return 'Falha ao ligar a API Vendus (HTTP '.$response->status().').';
    }
}
