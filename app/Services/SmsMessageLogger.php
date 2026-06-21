<?php

namespace App\Services;

use App\Models\SmsMessage;
use App\Support\PhoneDisplay;

final class SmsMessageLogger
{
    /**
     * @param  array{type?: string, store_id?: int, client_id?: int|null, client_name?: string|null, calendar_event_id?: int|null}  $context
     */
    public function logSuccessfulSend(
        string $toRaw,
        string $from,
        string $body,
        array $context,
        string $twilioSid,
        ?string $twilioStatus,
    ): void {
        $type = trim((string) ($context['type'] ?? ''));
        $storeId = (int) ($context['store_id'] ?? 0);

        if ($type === '' || $storeId <= 0) {
            return;
        }

        $toPhone = PhoneDisplay::toE164($toRaw) ?? trim($toRaw);
        $bodyForLog = $this->redactBody($type, $body);

        SmsMessage::query()->create([
            'store_id' => $storeId,
            'type' => $type,
            'client_id' => $context['client_id'] ?? null,
            'client_name' => isset($context['client_name']) ? trim((string) $context['client_name']) ?: null : null,
            'calendar_event_id' => $context['calendar_event_id'] ?? null,
            'to_phone' => $toPhone,
            'from_phone' => trim($from),
            'body' => $bodyForLog,
            'twilio_sid' => $twilioSid !== '' ? $twilioSid : null,
            'twilio_status' => $twilioStatus,
            'sent_at' => now(),
        ]);
    }

    private function redactBody(string $type, string $body): string
    {
        if (! in_array($type, [SmsMessage::TYPE_AUTH_OTP, SmsMessage::TYPE_CONTACT_VERIFICATION], true)) {
            return $body;
        }

        return (string) preg_replace('/\b\d{6}\b/', '******', $body);
    }
}
