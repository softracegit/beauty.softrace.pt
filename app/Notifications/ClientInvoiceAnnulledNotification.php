<?php

namespace App\Notifications;

use App\Models\CalendarEvent;
use App\Support\MarcacaoMailCopy;
use App\Support\StoreMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email ao cliente quando a fatura associada à marcação é anulada (checkout / Vendus).
 */
class ClientInvoiceAnnulledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $invoiceLabels  Números/labels das faturas anuladas (ex. FS 2026/1).
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

        $store = $event->store;
        $storeId = (int) ($event->store_id ?? 0) ?: null;
        $storeName = MarcacaoMailCopy::storeName($store);
        $greetingName = MarcacaoMailCopy::firstName($event->client?->name);

        $labels = array_values(array_filter(array_map('trim', $this->invoiceLabels)));
        $invoicesLine = count($labels) === 0
            ? "Informamos que a fatura associada à sua marcação {$storeName} foi anulada."
            : (count($labels) === 1
                ? 'Informamos que a seguinte fatura foi anulada: '.$labels[0].'.'
                : 'Informamos que as seguintes faturas foram anuladas: '.implode('; ', $labels).'.');

        $mail = (new MailMessage)
            ->subject(MarcacaoMailCopy::subject('Fatura anulada', $store))
            ->greeting($greetingName !== '' ? 'Olá '.$greetingName.',' : 'Olá,')
            ->line($invoicesLine)
            ->line(MarcacaoMailCopy::block([
                'Serviço: '.MarcacaoMailCopy::servicesLine($event),
                'Data da marcação: '.MarcacaoMailCopy::dateTime($event->start_at, $storeId),
            ]))
            ->line(MarcacaoMailCopy::spacer())
            ->line('O reembolso do valor pago será processado desde que se cumpram todas as condições de cancelamento e políticas aplicáveis ao seu caso.')
            ->line('Se tiver alguma questão ou dúvida, por favor contacte-nos.');

        $storeSlug = $store?->slug;
        if (is_string($storeSlug) && $storeSlug !== '') {
            $mail->action('Marcações online', route('booking.conta.marcacoes', ['store' => $storeSlug]));
        }

        return StoreMailBranding::applyToMailMessage($mail, $store);
    }
}
