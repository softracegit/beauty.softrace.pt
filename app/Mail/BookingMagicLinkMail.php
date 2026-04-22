<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingMagicLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $loginUrl,
        public string $purpose = 'login',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->purpose === 'password'
                ? 'Recuperar password da tua conta de marcação — '.config('app.name')
                : 'Acesso à tua conta de marcação — '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-magic-link-html',
        );
    }
}
