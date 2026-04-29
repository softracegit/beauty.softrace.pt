<?php

namespace App\Http\Controllers;

use App\Mail\BookingContactVerificationCodeMail;
use App\Models\BookingContactVerificationCode;
use App\Models\Client;
use App\Models\User;
use App\Services\TwilioSmsService;
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
        private readonly TwilioSmsService $twilioSmsService
    ) {}

    public function requestCode(Request $request): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $client = $user->loadMissing('client')->client;
        if (! $client instanceof Client) {
            throw ValidationException::withMessages([
                'verify' => ['Conta de cliente não encontrada.'],
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
                    'verify' => ['Defina um email válido na conta para poder verificar.'],
                ]);
            }
        } else {
            $target = PhoneDisplay::toE164((string) $client->phone);
            if ($target === null) {
                throw ValidationException::withMessages([
                    'verify' => ['Defina um telemóvel válido na conta para poder verificar.'],
                ]);
            }
        }

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
                Mail::mailer('booking')->to($target)->send(new BookingContactVerificationCodeMail($code, $ttlMinutes));
            } else {
                $this->twilioSmsService->send($target, sprintf('Código de verificação da conta: %s. Expira em %d minutos.', $code, $ttlMinutes));
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
                'verify' => ['Não foi possível enviar o código agora. Tente novamente em instantes.'],
            ]);
        }

        $message = $channel === 'email'
            ? 'Código enviado para o email.'
            : 'Código enviado por SMS para o telemóvel.';

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'channel' => $channel,
                'message' => $message,
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
                'verify' => ['Conta de cliente não encontrada.'],
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
                    'verify' => ['Defina um email válido antes de verificar.'],
                ]);
            }
        } else {
            $target = PhoneDisplay::toE164((string) $client->phone);
            if ($target === null) {
                throw ValidationException::withMessages([
                    'verify' => ['Defina um telemóvel válido antes de verificar.'],
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
                'verify' => ['Código inválido ou expirado. Peça um novo código.'],
            ]);
        }

        $authCode->attempts = (int) $authCode->attempts + 1;
        $isValid = hash_equals((string) $authCode->code_hash, hash('sha256', $code));
        if (! $isValid) {
            $authCode->save();
            throw ValidationException::withMessages([
                'verify' => ['Código inválido. Verifique e tente novamente.'],
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
            ? 'Email verificado com sucesso.'
            : 'Telemóvel verificado com sucesso.';

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
