<?php

namespace App\Jobs;

use App\Models\BookingSmsActionLink;
use App\Models\CalendarEvent;
use App\Models\SmsMessage;
use App\Services\TwilioSmsService;
use App\Support\BookingLocale;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class SendBookingReminderSmsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 30;

    public function __construct(public int $calendarEventId)
    {
        $this->tries = max(1, (int) config('booking.sms_reminder_max_attempts', 1));
    }

    public function handle(TwilioSmsService $sms): void
    {
        $lock = Cache::lock('booking_sms_reminder_event_'.$this->calendarEventId, 120);

        if (! $lock->get()) {
            return;
        }

        try {
            $event = CalendarEvent::query()
                ->with(['client:id,phone,notify_sms_booking_reminders', 'store:id,name,slug'])
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

            $tz = (string) config('booking.business_timezone', config('app.timezone'));
            $startAt = $event->start_at->copy()->timezone($tz);
            $storeName = trim((string) ($event->store?->name ?? config('app.name', 'A sua loja')));
            $expiresAt = now()->addHours(24);
            $actionLink = BookingSmsActionLink::query()
                ->where('calendar_event_id', $event->id)
                ->where('expires_at', '>', now())
                ->latest('id')
                ->first();
            if (! $actionLink) {
                $actionLink = BookingSmsActionLink::create([
                    'token' => $this->generateUniqueToken(),
                    'calendar_event_id' => $event->id,
                    'expires_at' => $expiresAt,
                ]);
            } else {
                $actionLink->expires_at = $expiresAt;
                $actionLink->save();
            }
            $manageUrl = URL::route('booking.sms.manage', ['token' => $actionLink->token]);

            $previousLocale = app()->getLocale();
            try {
                BookingLocale::apply(BookingLocale::fromPhone($client->phone));
                $whenLabel = $this->formatWhenLabel($startAt, $tz);
                $body = __('booking.sms.reminder_body', [
                    'store' => $storeName,
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

            CalendarEvent::query()
                ->whereKey($event->id)
                ->whereNull('booking_sms_reminder_sent_at')
                ->whereNull('booking_sms_reminder_failed_at')
                ->update([
                    'booking_sms_reminder_sent_at' => now(),
                    'status' => CalendarEvent::STATUS_NOTIFICADO,
                ]);
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

    private function generateUniqueToken(): string
    {
        do {
            $token = Str::lower(Str::random(10));
        } while (BookingSmsActionLink::query()->where('token', $token)->exists());

        return $token;
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
