<?php

namespace App\Notifications;

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

        $mail = (new MailMessage)
            ->subject('A sua fatura — '.$label)
            ->greeting($this->greetingName !== '' ? 'Olá '.$this->greetingName.',' : 'Olá,')
            ->line('Segue em anexo o documento fiscal emitido pela nossa faturação.')
            ->line('Documento: '.$label.'.')
            ->line('Obrigado pela preferência.');

        $mail->attachData($this->pdfBinary, $this->pdfFilename, [
            'mime' => 'application/pdf',
        ]);

        return $mail->salutation(config('app.name'));
    }
}
