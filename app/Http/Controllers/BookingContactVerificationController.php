<?php

namespace App\Http\Controllers;

use App\Mail\BookingContactVerificationCodeMail;
use App\Models\BookingContactVerificationCode;
use App\Models\Client;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\BookingOtpSendRateLimiter;
use App\Services\TwilioSmsService;
use App\Support\BookingLocale;
use App\Support\CurrentStore;
use App\Support\PhoneDisplay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingContactVerificationController extends Controller
{
    public function __construct(
        private readonly TwilioSmsService $twilioSmsService,
        private readonly BookingOtpSendRateLimiter $otpSendRateLimiter,
    ) {}

    public function requestCode(Request $request): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $client = $user->loadMissing('client')->client;
        if (! $client instanceof Client) {
            throw ValidationException::withMessages([
                'verify' => [__('booking.validation.verify_client_not_found')],
            ]);
        }

        $validated = $request->validate([
            'channel' => ['required', 'in:email,phone'],
        ]);
        $channel = (string) $validated['channel'];
        $ttlMinutes = max(3, (int) config('booking.auth_code_ttl_minutes', 10));
        $code = (string) random_int(100000, 999999);

        if ($channel === 'email') {
            $target = strtolower(trim((string) $user->email));
            if ($target === '') {
                throw ValidationException::withMessages([
                    'verify' => [__('booking.validation.verify_email_missing')],
                ]);
            }
        } else {
            $target = PhoneDisplay::toE164((string) $client->phone);
            if ($target === null) {
                throw ValidationException::withMessages([
                    'verify' => [__('booking.validation.verify_phone_missing')],
                ]);
            }
        }

        $rateBucket = 'vcontact:'.$user->id.':'.$channel;
        $this->otpSendRateLimiter->assertCanSend($rateBucket, 'verify');

        BookingContactVerificationCode::query()
            ->where('user_id', $user->id)
            ->where('channel', $channel)
            ->whereRaw('LOWER(target) = ?', [strtolower($target)])
            ->whereNull('consumed_at')
            ->delete();

        BookingContactVerificationCode::query()->create([
            'user_id' => $user->id,
            'channel' => $channel,
            'target' => $target,
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addMinutes($ttlMinutes),
            'requested_ip' => $request->ip(),
            'requested_user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
        ]);

        try {
            if ($channel === 'email') {
                $continueUrl = route('booking.index', ['store' => app(CurrentStore::class)->get()->slug]);
                $previousLocale = app()->getLocale();
                BookingLocale::apply(BookingLocale::emailLocale());
                try {
                    Mail::mailer('booking')
                        ->to($target)
                        ->send(new BookingContactVerificationCodeMail($code, $ttlMinutes, $continueUrl));
                } finally {
                    BookingLocale::apply($previousLocale);
                }
            } else {
                $previousLocale = app()->getLocale();
                try {
                    BookingLocale::apply(BookingLocale::fromPhone($target));
                    $this->twilioSmsService->send(
                        $target,
                        __('booking.sms.verification_code', ['code' => $code, 'minutes' => $ttlMinutes]),
                        [
                            'type' => SmsMessage::TYPE_CONTACT_VERIFICATION,
                            'store_id' => (int) $client->store_id,
                            'client_id' => $client->id,
                            'client_name' => $client->name,
                        ]
                    );
                } finally {
                    BookingLocale::apply($previousLocale);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Envio de código de verificação de contacto falhou.', [
                'user_id' => $user->id,
                'channel' => $channel,
                'target' => $target,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'verify' => [__('booking.validation.otp_send_failed')],
            ]);
        }

        $this->otpSendRateLimiter->recordSuccessfulSend($rateBucket);

        $message = $channel === 'email'
            ? __('booking.messages.verification_code_sent_email')
            : __('booking.messages.verification_code_sent_sms');

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'channel' => $channel,
                'message' => $message,
                'resend_cooldown_seconds' => max(0, (int) config('booking.otp_send_cooldown_seconds', 30)),
            ]);
        }

        return back()->with('success', $message);
    }

    public function confirmCode(Request $request): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $client = $user->loadMissing('client')->client;
        if (! $client instanceof Client) {
            throw ValidationException::withMessages([
                'verify' => [__('booking.validation.verify_client_not_found')],
            ]);
        }

        $validated = $request->validate([
            'channel' => ['required', 'in:email,phone'],
            'code' => ['required', 'digits:6'],
        ]);
        $channel = (string) $validated['channel'];
        $code = (string) $validated['code'];

        if ($channel === 'email') {
            $target = strtolower(trim((string) $user->email));
            if ($target === '') {
                throw ValidationException::withMessages([
                    'verify' => [__('booking.validation.verify_email_missing')],
                ]);
            }
        } else {
            $target = PhoneDisplay::toE164((string) $client->phone);
            if ($target === null) {
                throw ValidationException::withMessages([
                    'verify' => [__('booking.validation.verify_phone_missing')],
                ]);
            }
        }

        $authCode = BookingContactVerificationCode::query()
            ->where('user_id', $user->id)
            ->where('channel', $channel)
            ->whereRaw('LOWER(target) = ?', [strtolower($target)])
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $authCode) {
            throw ValidationException::withMessages([
                'verify' => [__('booking.validation.verify_code_invalid_expired')],
            ]);
        }

        $authCode->attempts = (int) $authCode->attempts + 1;
        $isValid = hash_equals((string) $authCode->code_hash, hash('sha256', $code));
        if (! $isValid) {
            $authCode->save();
            throw ValidationException::withMessages([
                'verify' => [__('booking.validation.verify_code_invalid')],
            ]);
        }

        $authCode->consumed_at = now();
        $authCode->save();

        if ($channel === 'email') {
            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();
        } else {
            $client->forceFill([
                'phone_verified_at' => now(),
            ])->save();
        }

        $message = $channel === 'email'
            ? __('booking.messages.email_verified')
            : __('booking.messages.phone_verified');

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'channel' => $channel,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }
}
