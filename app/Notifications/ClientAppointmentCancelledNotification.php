<?php

namespace App\Notifications;

use App\Models\CalendarEvent;
use App\Support\MarcacaoMailCopy;
use App\Support\StoreMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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
            ->with(['client', 'service', 'eventServices', 'store'])
            ->findOrFail($this->calendarEventId);

        $store = $event->store;
        $storeId = (int) ($event->store_id ?? 0) ?: null;
        $storeName = MarcacaoMailCopy::storeName($store);
        $greetingName = MarcacaoMailCopy::firstName($event->client?->name);

        $type = $event->cancellation_type ?? $event->status;
        $isFaltou = $type === CalendarEvent::STATUS_FALTOU;

        $mail = (new MailMessage)
            ->subject(MarcacaoMailCopy::subject(
                $isFaltou ? 'Informação sobre a sua marcação' : 'Marcação cancelada',
                $store,
            ))
            ->greeting($greetingName !== '' ? 'Olá '.$greetingName.',' : 'Olá,')
            ->line($isFaltou
                ? "Informamos que a sua marcação {$storeName} foi registada como falta (não comparecimento)."
                : "Informamos que a sua marcação {$storeName} foi cancelada.")
            ->line(MarcacaoMailCopy::block([
                ($isFaltou ? 'Data da marcação em que faltou: ' : 'Data da marcação cancelada: ')
                    .MarcacaoMailCopy::dateTime($event->start_at, $storeId),
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
