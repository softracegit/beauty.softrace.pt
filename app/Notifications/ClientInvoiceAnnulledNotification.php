<?php

namespace App\Notifications;

use App\Models\CalendarEvent;
use App\Support\DateTimeDisplay;
use App\Support\StoreMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email ao cliente quando a fatura (venda) associada à marcação é anulada na loja.
 * Mesmo estilo MailMessage que ClientAppointmentCreatedNotification / ClientAppointmentCancelledNotification.
 */
class ClientInvoiceAnnulledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $invoiceLabels  Rótulos das faturas anuladas (ex.: invoiceListLabel()).
     */
    public function __construct(
        public int $calendarEventId,
        public array $invoiceLabels = [],
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

        $start = DateTimeDisplay::marcacao($event->start_at, $storeId, 'd/m/Y \à\s H:i');

        $services = $event->eventServices->isNotEmpty()
            ? $event->eventServices
                ->map(function ($service) {
                    $optionName = trim((string) ($service->pivot->option_name ?? ''));

                    return $optionName !== '' ? $optionName : $service->name;
                })
                ->implode(', ')
            : ($event->service?->name ?? 'Marcação');

        $labels = array_values(array_filter(array_map('trim', $this->invoiceLabels)));
        $invoicesLine = count($labels) === 0
            ? 'Informamos que a fatura associada à sua marcação foi anulada.'
            : (count($labels) === 1
                ? 'Informamos que a seguinte fatura foi anulada: '.$labels[0].'.'
                : 'Informamos que as seguintes faturas foram anuladas: '.implode('; ', $labels).'.');

        $mail = (new MailMessage)
            ->subject('Fatura anulada')
            ->greeting($greetingName !== '' ? 'Olá '.$greetingName.',' : 'Olá,')
            ->line($invoicesLine)
            ->line("Marcação: «{$services}» ({$start}).")
            ->line('O reembolso do valor pago será processado desde que se cumpram todas as condições de cancelamento e políticas aplicáveis ao seu caso. Em caso de dúvida, contacte-nos.')
            ->line('Se tiver questões, estamos ao dispor.');

        $storeSlug = $event->store?->slug;
        if (is_string($storeSlug) && $storeSlug !== '') {
            $mail->action('Marcações online', route('booking.conta.marcacoes', ['store' => $storeSlug]));
        }

        return StoreMailBranding::applyToMailMessage($mail, $event->store);
    }
}
