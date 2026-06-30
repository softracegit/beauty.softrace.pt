<?php

namespace App\Mail;

use App\Models\Store;
use App\Support\StoreMailBranding;
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
        public ?Store $store = null,
    ) {}

    public function envelope(): Envelope
    {
        return StoreMailBranding::envelopeForStore(
            $this->store,
            __('booking.mail.auth_code_subject', ['app' => StoreMailBranding::resolve($this->store)['name']]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.booking-auth-code',
        );
    }
}
