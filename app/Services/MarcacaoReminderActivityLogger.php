<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Support\ActivityLogContext;

class MarcacaoReminderActivityLogger
{
    public const CHANNEL_SMS = 'sms';

    public const CHANNEL_EMAIL = 'email';

    public const TIMING_DAY_BEFORE = 'day_before';

    public const TIMING_SAME_DAY = 'same_day';

    public function logSmsDayBefore(CalendarEvent $event): void
    {
        $this->logSent($event, self::CHANNEL_SMS, self::TIMING_DAY_BEFORE);
    }

    public function logSmsSameDay(CalendarEvent $event): void
    {
        $this->logSent($event, self::CHANNEL_SMS, self::TIMING_SAME_DAY);
    }

    public function logEmailDayBefore(CalendarEvent $event): void
    {
        $this->logSent($event, self::CHANNEL_EMAIL, self::TIMING_DAY_BEFORE);
    }

    public function logEmailSameDay(CalendarEvent $event): void
    {
        $this->logSent($event, self::CHANNEL_EMAIL, self::TIMING_SAME_DAY);
    }

    public function logSent(CalendarEvent $event, string $channel, string $timing): void
    {
        $channelLabel = match ($channel) {
            self::CHANNEL_SMS => 'SMS',
            self::CHANNEL_EMAIL => 'Email',
            default => strtoupper($channel),
        };

        $timingLabel = match ($timing) {
            self::TIMING_DAY_BEFORE => 'dia anterior',
            self::TIMING_SAME_DAY => 'mesmo dia',
            default => $timing,
        };

        $eventName = $channel === self::CHANNEL_EMAIL ? 'lembrete_email' : 'lembrete_sms';
        $description = "{$channelLabel} de lembrete ({$timingLabel}) enviado";

        $properties = [
            'channel' => $channel,
            'timing' => $timing,
        ];

        $line = ActivityLogContext::marcacaoLine($event);
        if ($line !== null) {
            $properties['contexto'] = $line;
        }

        activity()
            ->performedOn($event)
            ->event($eventName)
            ->withProperties($properties)
            ->log($description);
    }
}
