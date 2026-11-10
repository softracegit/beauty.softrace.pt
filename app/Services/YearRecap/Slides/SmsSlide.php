<?php

namespace App\Services\YearRecap\Slides;

use App\Models\SmsMessage;
use App\Services\YearRecap\RecapContext;
use App\Services\YearRecap\RecapData;
use App\Services\YearRecap\RecapFormat as F;

/** SMS enviadas pela plataforma. Só admin. */
class SmsSlide implements RecapSlide
{
    public function key(): string
    {
        return 'sms';
    }

    public function allows(RecapContext $ctx): bool
    {
        return $ctx->audience === RecapContext::AUDIENCE_ADMIN;
    }

    public function build(RecapContext $ctx, RecapData $data): ?array
    {
        $byType = $data->smsByType();
        $total = (int) $byType->sum();
        if ($total < 10) {
            return null;
        }

        $labels = [
            SmsMessage::TYPE_BOOKING_REMINDER => 'Lembretes de marcação',
            SmsMessage::TYPE_AUTH_OTP => 'Códigos de acesso',
            SmsMessage::TYPE_CONTACT_VERIFICATION => 'Verificação de contacto',
            SmsMessage::TYPE_MARKETING => 'Marketing',
        ];

        return [
            'type' => 'stat',
            'kicker' => 'Mensagens',
            'label' => 'SMS enviadas',
            'value' => F::int($total),
            'body' => 'Lembretes, códigos e campanhas. Sem ninguém ter de pegar no telefone.',
            'list' => $byType->take(3)
                ->map(fn (int $n, string $type) => ['name' => $labels[$type] ?? SmsMessage::typeLabels()[$type] ?? $type, 'count' => F::int($n)])
                ->values()
                ->all(),
        ];
    }
}
