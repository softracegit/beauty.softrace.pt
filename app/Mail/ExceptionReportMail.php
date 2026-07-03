<?php

namespace App\Mail;

use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Throwable;

class ExceptionReportMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public Throwable $exception,
        public string $reference,
        public ?Request $request = null,
    ) {}

    public function envelope(): Envelope
    {
        $app = (string) config('app.name', 'App');
        $class = class_basename($this->exception);

        return new Envelope(
            subject: "[{$app}] Erro {$class} — ref. {$this->reference}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.exception-report',
            with: [
                'exceptionClass' => $this->exception::class,
                'exceptionMessage' => $this->exception->getMessage(),
                'exceptionFile' => $this->exception->getFile(),
                'exceptionLine' => $this->exception->getLine(),
                'stackTrace' => $this->trimStackTrace($this->exception),
                'url' => $this->request?->fullUrl(),
                'method' => $this->request?->method(),
                'userId' => $this->request?->user()?->getAuthIdentifier(),
                'storeId' => function_exists('current_store_id') ? current_store_id() : null,
                'input' => $this->safeInput(),
                'userAgent' => $this->request?->userAgent(),
                'ip' => $this->request?->ip(),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function safeInput(): array
    {
        if ($this->request === null) {
            return [];
        }

        return Arr::except($this->request->all(), [
            '_token',
            'password',
            'password_confirmation',
            'current_password',
            'new_password',
            'new_password_confirmation',
        ]);
    }

    private function trimStackTrace(Throwable $e): string
    {
        $trace = $e->getTraceAsString();
        $max = 12000;

        if (strlen($trace) <= $max) {
            return $trace;
        }

        return substr($trace, 0, $max)."\n… (trace truncado)";
    }
}
