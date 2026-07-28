<?php

namespace App\Notifications;

use App\Models\CalendarEvent;
use App\Support\MarcacaoMailCopy;
use App\Support\StoreMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientAppointmentReactivatedNotification extends Notification implements ShouldQueue
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

        $store = $event->store;
        $storeId = (int) ($event->store_id ?? 0) ?: null;
        $storeName = MarcacaoMailCopy::storeName($store);
        $greetingName = MarcacaoMailCopy::firstName($event->client?->name);

        $mail = (new MailMessage)
            ->subject(MarcacaoMailCopy::subject('Marcação reativada', $store))
            ->greeting($greetingName !== '' ? 'Olá '.$greetingName.',' : 'Olá,')
            ->line("Informamos que a sua marcação {$storeName} voltou a ficar ativa.")
            ->line(MarcacaoMailCopy::block([
                'Data: '.MarcacaoMailCopy::dateTime($event->start_at, $storeId),
                'Duração: '.MarcacaoMailCopy::duration($event->start_at, $event->end_at),
            ]))
            ->line(MarcacaoMailCopy::block([
                'Serviço: '.MarcacaoMailCopy::servicesLine($event),
            ]))
            ->line(MarcacaoMailCopy::spacer())
            ->line('Se tiver alguma questão ou dúvida, por favor contacte-nos.');

        $storeSlug = $store?->slug;
        if (is_string($storeSlug) && $storeSlug !== '') {
            $mail->action('Marcações online', route('booking.conta.marcacoes', ['store' => $storeSlug]));
        }

        return StoreMailBranding::applyToMailMessage($mail, $store);
    }
}
