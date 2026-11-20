<?php

namespace App\Jobs;

use App\Models\CalendarEvent;
use App\Models\SmsMessage;
use App\Services\MarcacaoReminderActivityLogger;
use App\Services\TwilioSmsService;
use App\Support\BookingLocale;
use App\Support\PhoneDisplay;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendBookingSameDayReminderSmsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 30;

    public int $tries = 1;

    /** @var list<string> */
    public const ELIGIBLE_STATUSES = [
        CalendarEvent::STATUS_AGENDADO,
        CalendarEvent::STATUS_NOTIFICADO,
        CalendarEvent::STATUS_CONFIRMADO,
    ];

    public function __construct(public int $calendarEventId)
    {
        $this->tries = max(1, (int) config('booking.sms_same_day_max_attempts', 1));
    }

    public function handle(TwilioSmsService $sms, MarcacaoReminderActivityLogger $reminderActivityLogger): void
    {
        $lock = Cache::lock('booking_sms_same_day_event_'.$this->calendarEventId, 120);

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

            if ($event->booking_sms_same_day_sent_at !== null || $event->booking_sms_same_day_failed_at !== null) {
                return;
            }

            if ($event->event_type !== CalendarEvent::TYPE_MARCACAO) {
                $this->markFailed('not_marcacao');

                return;
            }

            if (! in_array((string) $event->status, self::ELIGIBLE_STATUSES, true)) {
                $this->markFailed('status_not_eligible');

                return;
            }

            if (! $event->start_at || $event->start_at->lte(now())) {
                $this->markFailed('start_in_past');

                return;
            }

            $client = $event->client;
            if (! $client || ! $client->notify_sms_booking_reminders) {
                $this->markFailed('sms_opt_out');

                return;
            }

            if (! is_string($client->phone) || trim($client->phone) === '') {
                $this->markFailed('missing_phone');

                return;
            }

            if (PhoneDisplay::isBlockedForAlphanumericSender($client->phone)) {
                $this->markFailed('blocked_destination_country');

                return;
            }

            $tz = (string) config('booking.business_timezone', config('app.timezone'));
            $startAt = $event->start_at->copy()->timezone($tz);

            $previousLocale = app()->getLocale();
            try {
                BookingLocale::apply(BookingLocale::fromPhone($client->phone));
                $body = __('booking.sms.same_day_reminder_body', [
                    'time' => $startAt->format('H:i'),
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
                $this->markFailed('invalid_phone', $e->getMessage());

                return;
            }

            $updated = CalendarEvent::query()
                ->whereKey($event->id)
                ->whereNull('booking_sms_same_day_sent_at')
                ->whereNull('booking_sms_same_day_failed_at')
                ->update([
                    'booking_sms_same_day_sent_at' => now(),
                ]);

            if ($updated > 0) {
                $reminderActivityLogger->logSmsSameDay($event);
            }
        } catch (\Throwable $e) {
            Log::warning('booking_sms_same_day_failed', [
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
        $this->markFailed('twilio_error', $e?->getMessage());
    }

    private function markFailed(string $reason, ?string $detail = null): void
    {
        $updated = CalendarEvent::query()
            ->whereKey($this->calendarEventId)
            ->whereNull('booking_sms_same_day_sent_at')
            ->whereNull('booking_sms_same_day_failed_at')
            ->update([
                'booking_sms_same_day_failed_at' => now(),
            ]);

        if ($updated > 0) {
            Log::warning('booking_sms_same_day_marked_failed', [
                'calendar_event_id' => $this->calendarEventId,
                'reason' => $reason,
                'detail' => $detail,
            ]);
        }
    }
}
