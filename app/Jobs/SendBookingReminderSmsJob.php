<?php

namespace App\Jobs;

use App\Models\BookingSmsActionLink;
use App\Models\CalendarEvent;
use App\Models\SmsMessage;
use App\Services\MarcacaoReminderActivityLogger;
use App\Services\TwilioSmsService;
use App\Support\BookingLocale;
use App\Support\BookingPublicUrl;
use App\Support\PhoneDisplay;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendBookingReminderSmsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 30;

    public int $tries = 1;

    public function __construct(public int $calendarEventId)
    {
        $this->tries = max(1, (int) config('booking.sms_reminder_max_attempts', 1));
    }

    public function handle(TwilioSmsService $sms, MarcacaoReminderActivityLogger $reminderActivityLogger): void
    {
        $lock = Cache::lock('booking_sms_reminder_event_'.$this->calendarEventId, 120);

        if (! $lock->get()) {
            return;
        }

        try {
            $event = CalendarEvent::query()
                ->with([
                    'client:id,phone,notify_sms_booking_reminders',
                    'store:id,name,slug,organization_id',
                    'store.organization:id,name',
                ])
                ->find($this->calendarEventId);

            if (! $event) {
                return;
            }

            if ($event->booking_sms_reminder_sent_at !== null || $event->booking_sms_reminder_failed_at !== null) {
                return;
            }

            if ($event->event_type !== CalendarEvent::TYPE_MARCACAO) {
                $this->markReminderFailed('not_marcacao');

                return;
            }

            if ((string) $event->status !== CalendarEvent::STATUS_AGENDADO) {
                $this->markReminderFailed('status_not_agendado');

                return;
            }

            if (! $event->start_at || $event->start_at->lte(now())) {
                $this->markReminderFailed('start_in_past');

                return;
            }

            $client = $event->client;
            if (! $client || ! $client->notify_sms_booking_reminders) {
                $this->markReminderFailed('sms_opt_out');

                return;
            }

            if (! is_string($client->phone) || trim($client->phone) === '') {
                $this->markReminderFailed('missing_phone');

                return;
            }

            if (PhoneDisplay::isBlockedForAlphanumericSender($client->phone)) {
                $this->markReminderFailed('blocked_destination_country');

                return;
            }

            $tz = (string) config('booking.business_timezone', config('app.timezone'));
            $startAt = $event->start_at->copy()->timezone($tz);
            $actionLink = BookingSmsActionLink::findOrCreateForEvent($event);
            $manageUrl = BookingPublicUrl::smsConfirmUrl($actionLink->token, $event->store?->slug);

            $previousLocale = app()->getLocale();
            try {
                BookingLocale::apply(BookingLocale::fromPhone($client->phone));
                $whenLabel = $this->formatWhenLabel($startAt, $tz);
                $body = __('booking.sms.reminder_body', [
                    'when' => $whenLabel,
                    'url' => $manageUrl,
                ]);
            } finally {
                BookingLocale::apply($previousLocale);
            }

            try {
                $sms->send($client->phone, $body, [
                    'type' => SmsMessage::TYPE_BOOKING_REMINDER,
                    'store_id' => (int) ($event->store_id ?? 0),
                    'client_id' => $client->id,
                    'client_name' => $client->name,
                    'calendar_event_id' => $event->id,
                ]);
            } catch (\InvalidArgumentException $e) {
                $this->markReminderFailed('invalid_phone', $e->getMessage());

                return;
            }

            $updated = CalendarEvent::query()
                ->whereKey($event->id)
                ->whereNull('booking_sms_reminder_sent_at')
                ->whereNull('booking_sms_reminder_failed_at')
                ->update([
                    'booking_sms_reminder_sent_at' => now(),
                    'status' => CalendarEvent::STATUS_NOTIFICADO,
                ]);

            if ($updated > 0) {
                $reminderActivityLogger->logSmsDayBefore($event);
            }
        } catch (\Throwable $e) {
            Log::warning('booking_sms_reminder_failed', [
                'calendar_event_id' => $this->calendarEventId,
                'attempt' => $this->attempts(),
                'max_tries' => $this->tries,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            $lock->release();
        }
    }

    public function failed(?\Throwable $e): void
    {
        $this->markReminderFailed('twilio_error', $e?->getMessage());
    }

    private function markReminderFailed(string $reason, ?string $detail = null): void
    {
        $updated = CalendarEvent::query()
            ->whereKey($this->calendarEventId)
            ->whereNull('booking_sms_reminder_sent_at')
            ->whereNull('booking_sms_reminder_failed_at')
            ->update([
                'booking_sms_reminder_failed_at' => now(),
            ]);

        if ($updated > 0) {
            Log::warning('booking_sms_reminder_marked_failed', [
                'calendar_event_id' => $this->calendarEventId,
                'reason' => $reason,
                'detail' => $detail,
            ]);
        }
    }

    private function formatWhenLabel(\Carbon\CarbonInterface $startAt, string $timezone): string
    {
        $now = now($timezone);

        if ($startAt->isSameDay($now)) {
            return __('booking.sms.reminder_when_today', ['time' => $startAt->format('H:i')]);
        }

        if ($startAt->isSameDay($now->copy()->addDay())) {
            return __('booking.sms.reminder_when_tomorrow', ['time' => $startAt->format('H:i')]);
        }

        return __('booking.sms.reminder_when_date', [
            'date' => $startAt->format('d/m/Y'),
            'time' => $startAt->format('H:i'),
        ]);
    }
}
