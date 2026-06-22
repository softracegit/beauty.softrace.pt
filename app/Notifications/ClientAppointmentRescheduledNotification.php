<?php

namespace App\Notifications;

use App\Models\CalendarEvent;
use App\Support\DateTimeDisplay;
use App\Support\ReceptionNotificationMail;
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
            ->with(['client', 'service', 'eventServices', 'store'])
            ->findOrFail($this->calendarEventId);

        $storeId = (int) ($event->store_id ?? 0) ?: null;
        $clientName = $event->client?->name ?? '';
        $greetingName = $clientName !== '' ? explode(' ', trim($clientName), 2)[0] : '';

        $fmt = function ($dateTime) use ($storeId): string {
            return DateTimeDisplay::marcacao(
                $dateTime instanceof Carbon ? $dateTime : ($dateTime ? Carbon::parse($dateTime) : null),
                $storeId,
                'd/m/Y \à\s H:i',
            );
        };

        $newStart = $fmt($event->start_at);
        $newEnd = $fmt($event->end_at);
        $prevStart = $fmt($this->previousStartIso);
        $prevEnd = $fmt($this->previousEndIso);

        $services = $event->eventServices->isNotEmpty()
            ? $event->eventServices
                ->map(function ($service) {
                    $optionName = trim((string) ($service->pivot->option_name ?? ''));

                    return $optionName !== '' ? $optionName : $service->name;
                })
                ->implode(', ')
            : ($event->service?->name ?? 'Marcação');

        $subject = 'Marcação alterada';

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting($greetingName !== '' ? 'Olá '.$greetingName.',' : 'Olá,')
            ->line("A sua marcação de «{$services}» foi alterada.")
            ->line('Antes: '.$prevStart.' – '.$prevEnd.'.')
            ->line('Nova data/hora: '.$newStart.' – '.$newEnd.'.')
            ->line('Se tiver questões, contacte-nos.');

        $storeSlug = $event->store?->slug;
        if (is_string($storeSlug) && $storeSlug !== '') {
            $mail->action('Marcações online', route('booking.conta.marcacoes', ['store' => $storeSlug]));
        }

        ReceptionNotificationMail::applyReceptionCc(
            $mail,
            $event,
            array_filter([(string) ($event->client?->email ?? '')]),
        );

        return $mail->salutation(config('app.name'));
    }
}
