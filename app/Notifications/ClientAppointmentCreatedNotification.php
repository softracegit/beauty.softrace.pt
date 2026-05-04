<?php

namespace App\Notifications;

use App\Models\CalendarEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class ClientAppointmentCreatedNotification extends Notification implements ShouldQueue
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
            ->with(['client', 'service', 'eventServices', 'store'])
            ->findOrFail($this->calendarEventId);

        $tz = config('app.timezone');
        $clientName = $event->client?->name ?? '';
        $greetingName = $clientName !== '' ? explode(' ', trim($clientName), 2)[0] : '';

        $start = $event->start_at
            ? Carbon::parse($event->start_at)->timezone($tz)->format('d/m/Y \à\s H:i')
            : '—';
        $end = $event->end_at
            ? Carbon::parse($event->end_at)->timezone($tz)->format('H:i')
            : '—';

        $services = $event->eventServices->isNotEmpty()
            ? $event->eventServices
                ->map(function ($service) {
                    $optionName = trim((string) ($service->pivot->option_name ?? ''));

                    return $optionName !== '' ? $optionName : $service->name;
                })
                ->implode(', ')
            : ($event->service?->name ?? 'Marcação');

        $mail = (new MailMessage)
            ->subject('Marcação confirmada')
            ->greeting($greetingName !== '' ? 'Olá '.$greetingName.',' : 'Olá,')
            ->line("A sua marcação de «{$services}» foi confirmada.")
            ->line("Data/hora: {$start} – {$end}.")
            ->line('Se tiver questões, contacte-nos.');

        $storeSlug = $event->store?->slug;
        if (is_string($storeSlug) && $storeSlug !== '') {
            $mail->action('Marcações online', route('booking.conta.marcacoes', ['store' => $storeSlug]));
        }

        return $mail->salutation(config('app.name'));
    }
}

