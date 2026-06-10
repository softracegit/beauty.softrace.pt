<?php

namespace App\Mail;

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
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('booking.mail.verification_subject'),
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
