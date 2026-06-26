<?php

namespace App\Notifications;

use App\Models\CalendarEvent;
use App\Support\DateTimeDisplay;
use App\Support\ReceptionNotificationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientAppointmentCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $ccReceptionStaff;

    public function __construct(
        public int $calendarEventId,
        public bool $fromPublicBooking = false,
    ) {
        $this->ccReceptionStaff = ReceptionNotificationMail::shouldCcReceptionForActor(
            $fromPublicBooking,
            auth()->id(),
        );
    }

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

        $start = DateTimeDisplay::marcacao($event->start_at, $storeId, 'd/m/Y \à\s H:i');

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

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting($greetingName !== '' ? 'Olá '.$greetingName.',' : 'Olá,')
            ->line($line)
            ->line('Se tiver questões, contacte-nos.');

        $storeSlug = $event->store?->slug;
        if (is_string($storeSlug) && $storeSlug !== '') {
            $mail->action('Marcações online', route('booking.conta.marcacoes', ['store' => $storeSlug]));
        }

        if ($this->ccReceptionStaff) {
            ReceptionNotificationMail::applyReceptionCc(
                $mail,
                $event,
                array_filter([(string) ($event->client?->email ?? '')]),
            );
        }

        return $mail->salutation(config('app.name'));
    }
}
