<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Sale;
use App\Notifications\ClientVendusInvoiceNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

final class VendusInvoiceEmailService
{
    /**
     * @return array{sent: bool, message: string|null}
     */
    public function trySendToClient(Sale $sale): array
    {
        $sale->loadMissing('client');
        $client = $sale->client;
        if (! $this->clientAllowsEmailBookingUpdates($client)) {
            return ['sent' => false, 'message' => 'O cliente optou por não receber emails da loja.'];
        }

        $email = trim((string) ($client?->email ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['sent' => false, 'message' => 'Cliente sem email válido na ficha.'];
        }

        $pdf = $this->fetchVendusInvoicePdfBinaryWithRetry($sale);
        if ($pdf === null || $pdf === '') {
            Log::warning('vendus_invoice_pdf_unavailable_trying_internal_pdf', [
                'sale_id' => $sale->id,
                'vendus_document_id' => $sale->vendus_document_id,
            ]);
            $pdf = $this->renderInternalInvoicePdfBinary($sale);
            if ($pdf !== null && $pdf !== '') {
                Log::info('invoice_email_attach_internal_pdf_fallback', ['sale_id' => $sale->id]);
            }
        }
        if ($pdf === null || $pdf === '') {
            return ['sent' => false, 'message' => 'Não foi possível gerar o PDF da fatura para envio por email. Pode imprimir o recibo ou tentar «Enviar por email» na marcação mais tarde.'];
        }

        $clientName = trim((string) ($client?->name ?? ''));
        $greeting = $clientName !== '' ? explode(' ', $clientName, 2)[0] : '';
        $safeLabel = str_replace(['/', '\\'], '-', (string) $sale->numero_fatura);
        $filename = 'fatura-'.$safeLabel.'.pdf';

        try {
            Notification::route('mail', $this->resolveClientNotificationRecipientEmail($email))
                ->notify(new ClientVendusInvoiceNotification(
                    $greeting,
                    (string) $sale->numero_fatura,
                    $filename,
                    $pdf,
                    (int) ($sale->store_id ?? 0) ?: null,
                    (int) ($sale->calendar_event_id ?? 0) ?: null,
                ));

            return ['sent' => true, 'message' => null];
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar email com fatura Vendus ao cliente.', [
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
            ]);

            return ['sent' => false, 'message' => 'Não foi possível enviar o email. Tente imprimir a fatura.'];
        }
    }

    /**
     * A API Vendus pode demorar alguns segundos a servir o PDF após criar o documento.
     */
    public function fetchVendusInvoicePdfBinaryWithRetry(Sale $sale, int $maxAttempts = 12, int $delayMs = 400): ?string
    {
        return $this->fetchVendusDocumentPdfBinaryWithRetry((int) ($sale->vendus_document_id ?? 0), $maxAttempts, $delayMs, (int) $sale->id);
    }

    /**
     * Obtém o PDF oficial da nota de crédito (documento associado à venda anulada).
     */
    public function fetchVendusCreditNotePdfBinaryWithRetry(Sale $sale, int $maxAttempts = 12, int $delayMs = 400): ?string
    {
        return $this->fetchVendusDocumentPdfBinaryWithRetry((int) ($sale->vendus_credit_note_id ?? 0), $maxAttempts, $delayMs, (int) $sale->id);
    }

    public function fetchVendusDocumentPdfBinaryWithRetry(int $documentId, int $maxAttempts = 12, int $delayMs = 400, ?int $saleId = null): ?string
    {
        if ($documentId <= 0) {
            return null;
        }

        for ($i = 0; $i < $maxAttempts; $i++) {
            if ($i > 0) {
                usleep($delayMs * 1000);
            }
            $binary = $this->fetchVendusDocumentPdfBinary($documentId);
            if ($binary !== null && $binary !== '') {
                if ($i > 0) {
                    Log::info('vendus_invoice_pdf_ready_after_retry', [
                        'sale_id' => $saleId,
                        'vendus_document_id' => $documentId,
                        'attempt' => $i + 1,
                    ]);
                }

                return $binary;
            }
        }

        return null;
    }

    /**
     * Obtém o binário do PDF oficial na Vendus para anexar ao email ou devolver ao browser.
     */
    private function fetchVendusInvoicePdfBinary(Sale $sale): ?string
    {
        return $this->fetchVendusDocumentPdfBinary((int) ($sale->vendus_document_id ?? 0));
    }

    private function fetchVendusDocumentPdfBinary(int $documentId): ?string
    {
        if ($documentId <= 0) {
            return null;
        }

        $baseUrl = rtrim((string) config('services.vendus.base_url'), '/');
        $apiKey = (string) config('services.vendus.api_key');
        $authMode = strtolower((string) config('services.vendus.auth_mode', 'basic'));
        $vendusMode = (string) config('services.vendus.mode', 'normal');

        if ($baseUrl === '' || $apiKey === '') {
            return null;
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
                return $response->body();
            }

            $pdfUrl = $this->extractPdfUrlFromVendusPayload($response->json());
            if ($pdfUrl !== null) {
                $binary = $this->fetchVendusPdfBinary($request, $pdfUrl, $apiKey, $authMode, $baseUrl);
                if ($binary !== null) {
                    return $binary;
                }
            }
        }

        return null;
    }

    /**
     * PDF interno (DomPDF) — mesmo conteúdo que GET sales/{sale}/pdf; usado como fallback no email
     * quando o PDF oficial da Vendus não está acessível pela API.
     */
    private function renderInternalInvoicePdfBinary(Sale $sale): ?string
    {
        try {
            $sale->load(['client', 'items', 'calendarEvent.eventServiceItems.extras.extra']);
            $pdf = Pdf::loadView('pdf.invoice', ['sale' => $sale])->setPaper('a4', 'portrait');

            return $pdf->output();
        } catch (\Throwable $e) {
            Log::warning('internal_invoice_pdf_render_failed', [
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function vendusGetWithAuth($request, string $url, string $apiKey, string $authMode): Response
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

    /**
     * Evita enviar emails de testes para clientes reais (igual CheckoutController).
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
}
