<?php

namespace App\Notifications;

use App\Models\CalendarEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class ClientAppointmentCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $calendarEventId,
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

        $start = $event->start_at
            ? Carbon::parse($event->start_at)->timezone($tz)->format('d/m/Y \à\s H:i')
            : '—';

        $services = $event->eventServices->isNotEmpty()
            ? $event->eventServices
                ->map(function ($service) {
                    $optionName = trim((string) ($service->pivot->option_name ?? ''));

                    return $optionName !== '' ? $optionName : $service->name;
                })
                ->implode(', ')
            : ($event->service?->name ?? 'Marcação');

        $type = $event->cancellation_type ?? $event->status;
        $isFaltou = $type === CalendarEvent::STATUS_FALTOU;

        $subject = $isFaltou
            ? 'Informação sobre a sua marcação'
            : 'Marcação cancelada';

        $line = $isFaltou
            ? "Informamos que a sua marcação de «{$services}» agendada para {$start} foi registada como falta (não comparecimento)."
            : "Informamos que a sua marcação de «{$services}» agendada para {$start} foi cancelada.";

        return (new MailMessage)
            ->subject($subject)
            ->greeting($greetingName !== '' ? 'Olá '.$greetingName.',' : 'Olá,')
            ->line($line)
            ->line('Se tiver questões, contacte-nos.')
            ->salutation(config('app.name'));
    }
}
