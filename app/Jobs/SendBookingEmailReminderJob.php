<?php

namespace App\Jobs;

use App\Models\CalendarEvent;
use App\Notifications\ClientAppointmentReminderNotification;
use App\Services\MarcacaoReminderActivityLogger;
use App\Support\BookingLocale;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendBookingEmailReminderJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $tries = 1;

    /**
     * Inclui notificado/confirmado: o SMS do dia anterior pode ter passado agendado → notificado
     * antes do email (canais independentes).
     *
     * @var list<string>
     */
    public const ELIGIBLE_STATUSES = [
        CalendarEvent::STATUS_AGENDADO,
        CalendarEvent::STATUS_NOTIFICADO,
        CalendarEvent::STATUS_CONFIRMADO,
    ];

    public function __construct(public int $calendarEventId) {}

    public function handle(MarcacaoReminderActivityLogger $reminderActivityLogger): void
    {
        $lock = Cache::lock('booking_email_reminder_event_'.$this->calendarEventId, 120);

        if (! $lock->get()) {
            return;
        }

        try {
            $event = CalendarEvent::query()
                ->with(['client:id,email,name,notify_email_booking_reminders'])
                ->find($this->calendarEventId);

            if (! $event) {
                return;
            }

            if ($event->booking_email_reminder_sent_at !== null || $event->booking_email_reminder_failed_at !== null) {
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
            if (! $client || ! $client->notify_email_booking_reminders) {
                $this->markFailed('email_opt_out');

                return;
            }

            $email = trim((string) ($client->email ?? ''));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->markFailed('missing_or_invalid_email');

                return;
            }

            Notification::route('mail', $email)
                ->notify(
                    (new ClientAppointmentReminderNotification((int) $event->id))
                        ->locale(BookingLocale::emailLocale())
                );

            $updated = CalendarEvent::query()
                ->whereKey($event->id)
                ->whereNull('booking_email_reminder_sent_at')
                ->whereNull('booking_email_reminder_failed_at')
                ->update([
                    'booking_email_reminder_sent_at' => now(),
                ]);

            if ($updated > 0) {
                $reminderActivityLogger->logEmailDayBefore($event);
            }
        } catch (\Throwable $e) {
            Log::warning('booking_email_reminder_failed', [
                'calendar_event_id' => $this->calendarEventId,
                'message' => $e->getMessage(),
            ]);

            $this->markFailed('send_error', $e->getMessage());
        } finally {
            $lock->release();
        }
    }

    public function failed(?\Throwable $e): void
    {
        $this->markFailed('job_failed', $e?->getMessage());
    }

    private function markFailed(string $reason, ?string $detail = null): void
    {
        $updated = CalendarEvent::query()
            ->whereKey($this->calendarEventId)
            ->whereNull('booking_email_reminder_sent_at')
            ->whereNull('booking_email_reminder_failed_at')
            ->update([
                'booking_email_reminder_failed_at' => now(),
            ]);

        if ($updated > 0) {
            Log::warning('booking_email_reminder_marked_failed', [
                'calendar_event_id' => $this->calendarEventId,
                'reason' => $reason,
                'detail' => $detail,
            ]);
        }
    }
}
