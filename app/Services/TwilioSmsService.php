<?php

namespace App\Services;

use App\Support\PhoneDisplay;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class TwilioSmsService
{
    public function isConfigured(): bool
    {
        $c = config('services.twilio');

        return ! empty($c['account_sid']) && ! empty($c['auth_token']) && ! empty($c['sms_from']);
    }

    /**
     * @return array{sid: string, status: string|null}
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function send(string $toRaw, string $body): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Twilio não está configurado. Em local use TWILIO_ACCOUNT_SID_SANDBOX/TWILIO_AUTH_TOKEN_SANDBOX (ou as reais) e defina TWILIO_SMS_FROM no .env.');
        }

        $to = PhoneDisplay::toE164($toRaw);
        if ($to === null) {
            throw new \InvalidArgumentException('Número de telefone inválido. Use formato internacional ou um número português.');
        }

        $sid = config('services.twilio.account_sid');
        $token = config('services.twilio.auth_token');
        $from = config('services.twilio.sms_from');

        $url = sprintf(
            'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json',
            rawurlencode($sid)
        );

        /** @var Response $response */
        $response = Http::withBasicAuth($sid, $token)
            ->asForm()
            ->acceptJson()
            ->timeout(30)
            ->post($url, [
                'To' => $to,
                'From' => $from,
                'Body' => $body,
            ]);

        if ($response->successful()) {
            $data = $response->json();

            return [
                'sid' => (string) ($data['sid'] ?? ''),
                'status' => isset($data['status']) ? (string) $data['status'] : null,
            ];
        }

        $this->logFailure($response);

        $message = $this->extractTwilioErrorMessage($response);

        throw new \RuntimeException($message);
    }

    private function logFailure(Response $response): void
    {
        $json = $response->json();
        Log::warning('twilio_sms_http_error', [
            'status' => $response->status(),
            'code' => $json['code'] ?? null,
            'message' => $json['message'] ?? $response->body(),
        ]);
    }

    private function extractTwilioErrorMessage(Response $response): string
    {
        $json = $response->json();
        if (is_array($json) && ! empty($json['message'])) {
            return (string) $json['message'];
        }

        return 'Falha ao enviar SMS (HTTP '.$response->status().').';
    }
}
