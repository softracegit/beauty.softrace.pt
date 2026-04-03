<?php

namespace App\Notifications;

use App\Models\CalendarEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class ClientAppointmentRescheduledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $calendarEventId,
        public ?string $previousStartIso,
        public ?string $previousEndIso,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = CalendarEvent::query()
            ->with(['client', 'service', 'eventServices'])
            ->findOrFail($this->calendarEventId);

        $tz = config('app.timezone');
        $clientName = $event->client?->name ?? '';
        $greetingName = $clientName !== '' ? explode(' ', trim($clientName), 2)[0] : '';

        $fmt = function (?Carbon $dt) use ($tz): string {
            if (! $dt) {
                return '—';
            }

            return $dt->timezone($tz)->format('d/m/Y \à\s H:i');
        };

        $newStart = $event->start_at ? $fmt(Carbon::parse($event->start_at)) : '—';
        $newEnd = $event->end_at ? $fmt(Carbon::parse($event->end_at)) : '—';

        $prevStart = $this->previousStartIso ? $fmt(Carbon::parse($this->previousStartIso)) : '—';
        $prevEnd = $this->previousEndIso ? $fmt(Carbon::parse($this->previousEndIso)) : '—';

        $services = $event->eventServices->isNotEmpty()
            ? $event->eventServices->pluck('name')->implode(', ')
            : ($event->service?->name ?? 'Marcação');

        $subject = 'Marcação alterada';

        return (new MailMessage)
            ->subject($subject)
            ->greeting($greetingName !== '' ? 'Olá '.$greetingName.',' : 'Olá,')
            ->line("A sua marcação de «{$services}» foi alterada.")
            ->line('Antes: '.$prevStart.' – '.$prevEnd.'.')
            ->line('Nova data/hora: '.$newStart.' – '.$newEnd.'.')
            ->line('Se tiver questões, contacte-nos.')
            ->salutation(config('app.name'));
    }
}
