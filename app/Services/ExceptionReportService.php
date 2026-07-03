<?php

namespace App\Services;

use App\Mail\ExceptionReportMail;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ExceptionReportService
{
    public const REFERENCE_KEY = 'error_report.reference';

    public function shouldReport(Throwable $e): bool
    {
        if (! config('errors.report_enabled', false)) {
            return false;
        }

        if ($e instanceof ValidationException) {
            return false;
        }

        if ($e instanceof AuthenticationException || $e instanceof AuthorizationException) {
            return false;
        }

        if ($e instanceof ModelNotFoundException) {
            return false;
        }

        if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
            return false;
        }

        return true;
    }

    public function report(Throwable $e, ?Request $request = null): ?string
    {
        if (! $this->shouldReport($e)) {
            return null;
        }

        $request ??= request();
        $reference = (string) Str::uuid();
        app()->instance(self::REFERENCE_KEY, $reference);

        if (! $this->shouldSendEmail($e)) {
            return $reference;
        }

        $recipients = $this->resolveRecipients();
        if ($recipients === []) {
            Log::warning('Exception report skipped: no ERROR_REPORT_EMAIL configured.', [
                'reference' => $reference,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $reference;
        }

        try {
            Mail::to($recipients)->send(new ExceptionReportMail($e, $reference, $request));
        } catch (Throwable $mailError) {
            Log::error('Failed to send exception report email.', [
                'reference' => $reference,
                'mail_error' => $mailError->getMessage(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }

        return $reference;
    }

    public static function currentReference(): ?string
    {
        if (! app()->bound(self::REFERENCE_KEY)) {
            return null;
        }

        $reference = app(self::REFERENCE_KEY);

        return is_string($reference) && $reference !== '' ? $reference : null;
    }

    /**
     * @return list<string>
     */
    private function resolveRecipients(): array
    {
        $configured = config('errors.report_recipients', []);
        if (is_array($configured) && $configured !== []) {
            return array_values(array_filter($configured, static fn ($email) => is_string($email) && $email !== ''));
        }

        $fallback = config('mail.from.address');

        return is_string($fallback) && $fallback !== '' ? [$fallback] : [];
    }

    private function shouldSendEmail(Throwable $e): bool
    {
        $fingerprint = sha1(implode('|', [
            $e::class,
            $e->getFile(),
            (string) $e->getLine(),
            $e->getMessage(),
        ]));

        $minutes = (int) config('errors.report_throttle_minutes', 15);
        $cacheKey = 'error_report:sent:'.$fingerprint;

        return Cache::add($cacheKey, true, now()->addMinutes($minutes));
    }
}
