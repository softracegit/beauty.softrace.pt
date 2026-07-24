<?php

namespace App\Mail;

use App\Models\Store;
use App\Support\StoreMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingContactVerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly int $ttlMinutes,
        public readonly ?string $continueUrl = null,
        public readonly ?Store $store = null,
    ) {}

    public function envelope(): Envelope
    {
        return StoreMailBranding::envelopeForStore(
            $this->store,
            __('booking.mail.verification_subject', ['app' => StoreMailBranding::resolve($this->store)['name']]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-contact-verification-code',
            with: [
                'code' => $this->code,
                'ttlMinutes' => $this->ttlMinutes,
                'continueUrl' => $this->continueUrl,
            ]
        );
    }
}
