<?php

namespace App\Notifications;

use App\Models\BookingSmsActionLink;
use App\Models\CalendarEvent;
use App\Support\BookingPublicUrl;
use App\Support\MarcacaoMailCopy;
use App\Support\StoreMailBranding;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Lembrete por email (dia anterior ou próprio dia).
 * Dia anterior: com botão confirmar. Próprio dia: sem botão.
 */
class ClientAppointmentReminderNotification extends Notification
{
    public function __construct(
        public int $calendarEventId,
        public bool $includeConfirmAction = true,
        public bool $isSameDay = false,
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
            ->with(['client', 'service', 'eventServices', 'store.organization'])
            ->findOrFail($this->calendarEventId);

        $store = $event->store;
        $storeId = (int) ($event->store_id ?? 0) ?: null;
        $greetingName = MarcacaoMailCopy::firstName($event->client?->name);
        $reminderLead = $this->isSameDay
            ? 'Lembramos da sua marcação hoje.'
            : 'Lembramos da sua marcação amanhã.';

        $mail = (new MailMessage)
            ->subject(MarcacaoMailCopy::subject('Lembrete de marcação', $store))
            ->greeting($greetingName !== '' ? 'Olá '.$greetingName.',' : 'Olá,')
            ->line($reminderLead)
            ->line(MarcacaoMailCopy::block([
                'Data: '.MarcacaoMailCopy::dateTime($event->start_at, $storeId),
                'Duração: '.MarcacaoMailCopy::duration($event->start_at, $event->end_at),
            ]))
            ->line(MarcacaoMailCopy::block([
                'Serviço: '.MarcacaoMailCopy::servicesLine($event),
            ]));

        if ($this->includeConfirmAction && $event->start_at !== null) {
            $actionLink = BookingSmsActionLink::findOrCreateForEvent($event);
            $confirmUrl = BookingPublicUrl::smsConfirmUrl($actionLink->token, $store?->slug);
            $mail->action('Confirmar marcação', $confirmUrl);
        }

        $mail->line(MarcacaoMailCopy::spacer())
            ->line('Se tiver alguma questão ou dúvida, por favor contacte-nos.');

        return StoreMailBranding::applyToMailMessage($mail, $store);
    }
}
