<?php

namespace App\Jobs;

use App\Models\BookingSmsActionLink;
use App\Models\CalendarEvent;
use App\Services\TwilioSmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class SendBookingReminderSmsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public int $calendarEventId) {}

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

            if ($event->event_type !== CalendarEvent::TYPE_MARCACAO) {
                return;
            }

            if ((string) $event->status !== CalendarEvent::STATUS_AGENDADO) {
                return;
            }

            if ($event->booking_sms_reminder_sent_at !== null) {
                return;
            }

            if (! $event->start_at || $event->start_at->lte(now())) {
                return;
            }

            $client = $event->client;
            if (! $client || ! $client->notify_sms_booking_reminders || ! is_string($client->phone) || trim($client->phone) === '') {
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
            $whenLabel = $this->formatWhenLabel($startAt, $tz);
            $body = sprintf(
                "%s lembra da sua marcacao %s\n\nConfirmar marcação:\n%s\n\nObrigado\n+351300505149 / +351928116651",
                $storeName,
                $whenLabel,
                $manageUrl
            );

            $sms->send($client->phone, $body);

            CalendarEvent::query()
                ->whereKey($event->id)
                ->whereNull('booking_sms_reminder_sent_at')
                ->update([
                    'booking_sms_reminder_sent_at' => now(),
                    'status' => CalendarEvent::STATUS_NOTIFICADO,
                ]);
        } catch (\Throwable $e) {
            Log::warning('booking_sms_reminder_failed', [
                'calendar_event_id' => $this->calendarEventId,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            $lock->release();
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
            return 'hoje às '.$startAt->format('H:i');
        }

        if ($startAt->isSameDay($now->copy()->addDay())) {
            return 'amanhã às '.$startAt->format('H:i');
        }

        return sprintf('em %s às %s', $startAt->format('d/m/Y'), $startAt->format('H:i'));
    }
}
