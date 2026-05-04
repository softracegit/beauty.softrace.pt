<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingAuthCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public int $ttlMinutes,
        public ?string $continueUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        // Separador: hífen ASCII (-), nunca travessão (—), para cabeçalhos Subject.
        return new Envelope(
            subject: 'Codigo de acesso - '.(string) config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.booking-auth-code',
        );
    }
}
