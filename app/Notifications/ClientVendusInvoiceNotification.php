<?php

namespace App\Notifications;

use App\Models\CalendarEvent;
use App\Models\Store;
use App\Support\MarcacaoMailCopy;
use App\Support\StoreMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email ao cliente com o PDF oficial da fatura (Vendus) após pagamento na agenda ou reserva online.
 */
class ClientVendusInvoiceNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $greetingName,
        public string $invoiceLabel,
        public string $pdfFilename,
        public string $pdfBinary,
        public ?int $storeId = null,
        public ?int $calendarEventId = null,
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
        $label = trim($this->invoiceLabel) !== '' ? $this->invoiceLabel : 'Fatura';
        $store = $this->storeId ? Store::query()->find($this->storeId) : null;
        $storeName = MarcacaoMailCopy::storeName($store);

        $event = null;
        if ($this->calendarEventId) {
            $event = CalendarEvent::query()
                ->with(['service', 'eventServices'])
                ->find($this->calendarEventId);
        }

        $eventStoreId = $event ? ((int) ($event->store_id ?? 0) ?: $this->storeId) : $this->storeId;
        $dateLine = $event
            ? MarcacaoMailCopy::dateTime($event->start_at, $eventStoreId)
            : '-';
        $servicesLine = $event
            ? MarcacaoMailCopy::servicesLine($event)
            : '-';

        $mail = (new MailMessage)
            ->subject(MarcacaoMailCopy::subject('A sua fatura - '.$label, $store))
            ->greeting($this->greetingName !== '' ? 'Olá '.$this->greetingName.',' : 'Olá,')
            ->line("Segue em anexo a sua Fatura relativa à sua marcação {$storeName}")
            ->line(MarcacaoMailCopy::block([
                'Documento: '.$label.'.',
                'Data da marcação realizada: '.$dateLine,
                'Serviço: '.$servicesLine,
            ]))
            ->line(MarcacaoMailCopy::spacer())
            ->line('Obrigado pela preferência!')
            ->line('Se tiver alguma questão ou dúvida, por favor contacte-nos.');

        $mail->attachData($this->pdfBinary, $this->pdfFilename, [
            'mime' => 'application/pdf',
        ]);

        return StoreMailBranding::applyToMailMessage($mail, $store);
    }
}
