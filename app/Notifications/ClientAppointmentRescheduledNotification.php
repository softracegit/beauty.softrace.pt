<?php

namespace App\Notifications;

use App\Models\CalendarEvent;
use App\Support\MarcacaoMailCopy;
use App\Support\StoreMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientAppointmentRescheduledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $servicesAdded
     * @param  list<string>  $servicesRemoved
     */
    public function __construct(
        public int $calendarEventId,
        public ?string $previousStartIso,
        public ?string $previousEndIso,
        public array $servicesAdded = [],
        public array $servicesRemoved = [],
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

        $store = $event->store;
        $storeId = (int) ($event->store_id ?? 0) ?: null;
        $storeName = MarcacaoMailCopy::storeName($store);
        $greetingName = MarcacaoMailCopy::firstName($event->client?->name);

        $prevStart = MarcacaoMailCopy::parseIso($this->previousStartIso);
        $datesChanged = MarcacaoMailCopy::startsDiffer($prevStart, $event->start_at, $storeId);

        $scheduleLines = $datesChanged
            ? [
                'Data anterior: '.MarcacaoMailCopy::dateTime($prevStart, $storeId),
                'Nova Data: '.MarcacaoMailCopy::dateTime($event->start_at, $storeId),
                'Duração: '.MarcacaoMailCopy::duration($event->start_at, $event->end_at),
            ]
            : [
                'Data: '.MarcacaoMailCopy::dateTime($event->start_at, $storeId),
                'Duração: '.MarcacaoMailCopy::duration($event->start_at, $event->end_at),
            ];

        $detailLines = [
            ...MarcacaoMailCopy::serviceChangeLines($this->servicesAdded, $this->servicesRemoved),
            'Serviço: '.MarcacaoMailCopy::servicesLine($event),
        ];

        $mail = (new MailMessage)
            ->subject(MarcacaoMailCopy::subject('Marcação alterada', $store))
            ->greeting($greetingName !== '' ? 'Olá '.$greetingName.',' : 'Olá,')
            ->line("A sua marcação {$storeName} foi alterada.")
            ->line(MarcacaoMailCopy::block($scheduleLines))
            ->line(MarcacaoMailCopy::block($detailLines))
            ->line(MarcacaoMailCopy::spacer())
            ->line('Se tiver alguma questão ou dúvida, por favor contacte-nos.');

        $storeSlug = $store?->slug;
        if (is_string($storeSlug) && $storeSlug !== '') {
            $mail->action('Marcações online', route('booking.conta.marcacoes', ['store' => $storeSlug]));
        }

        return StoreMailBranding::applyToMailMessage($mail, $store);
    }
}
