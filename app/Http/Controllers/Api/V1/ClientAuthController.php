<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BookingClientAuthController;
use App\Http\Controllers\Controller;
use App\Models\ClientApiToken;
use App\Models\Store;
use App\Models\User;
use App\Support\Api\ClientApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

/**
 * Entrada sem palavra-passe na app (código por email ou SMS).
 *
 * Reutiliza o BookingClientAuthController do booking web (mesmas regras, mesmos
 * emails/SMS, mesmos limites), numa sessão em memória (UseArraySessionForApi).
 * Em vez de cookie de sessão, devolve um token para a app guardar.
 */
class ClientAuthController extends Controller
{
    /** Minutos para concluir o registo depois de validar o código. */
    private const REGISTRATION_TOKEN_MINUTES = 30;

    public function __construct(private readonly BookingClientAuthController $web) {}

    /** POST /api/v1/stores/{store}/auth/request-code  { login } */
    public function requestCode(Request $request, Store $store): JsonResponse
    {
        $response = $this->web->requestCodeFromAuthModal($request);
        $data = $response->getData(true);

        return response()->json([
            'channel' => $data['channel'] ?? null,
            'identifier' => $data['identifier'] ?? null,
            'expires_in' => $data['expires_in'] ?? null,
            'known_account' => (bool) ($data['known_account'] ?? false),
            'resend_cooldown_seconds' => $data['resend_cooldown_seconds'] ?? 30,
        ]);
    }

    /** POST /api/v1/stores/{store}/auth/verify-code  { login, code, device_name? } */
    public function verifyCode(Request $request, Store $store): JsonResponse
    {
        $response = $this->web->verifyCodeFromAuthModal($request);
        $data = $response->getData(true);

        if (! empty($data['requires_registration'])) {
            return response()->json([
                'requires_registration' => true,
                'channel' => $data['channel'],
                'identifier' => $data['identifier'],
                'registration_prefill' => $data['registration_prefill'] ?? null,
                'registration_token' => Crypt::encryptString(json_encode([
                    'channel' => $data['channel'],
                    'identifier' => $data['identifier'],
                    'store_id' => (int) $store->id,
                    'exp' => now()->addMinutes(self::REGISTRATION_TOKEN_MINUTES)->timestamp,
                ])),
            ]);
        }

        return $this->issueTokenForLoggedInUser($request, $store);
    }

    /**
     * POST /api/v1/stores/{store}/auth/complete-registration
     * { registration_token, name, email?, phone?, terms_accepted, device_name? }
     */
    public function completeRegistration(Request $request, Store $store): JsonResponse
    {
        $request->validate(['registration_token' => ['required', 'string', 'max:2000']]);

        try {
            $payload = json_decode(Crypt::decryptString((string) $request->input('registration_token')), true);
        } catch (\Throwable) {
            $payload = null;
        }
        if (! is_array($payload)
            || (int) ($payload['store_id'] ?? 0) !== (int) $store->id
            || (int) ($payload['exp'] ?? 0) < now()->timestamp
            || ! in_array($payload['channel'] ?? '', ['email', 'phone'], true)
        ) {
            throw ValidationException::withMessages([
                'login' => [__('booking.validation.registration_session_expired')],
            ]);
        }

        $request->session()->put('booking_auth.pending_registration.channel', $payload['channel']);
        $request->session()->put('booking_auth.pending_registration.identifier', (string) $payload['identifier']);

        $this->web->completeRegistrationFromAuthModal($request);

        return $this->issueTokenForLoggedInUser($request, $store, isNew: true);
    }

    /** POST /api/v1/stores/{store}/auth/logout  (com token) */
    public function logout(Request $request, Store $store): JsonResponse
    {
        $token = $request->attributes->get('client_api_token');
        if ($token instanceof ClientApiToken) {
            $token->delete();
        }

        return response()->json(['ok' => true]);
    }

    private function issueTokenForLoggedInUser(Request $request, Store $store, bool $isNew = false): JsonResponse
    {
        $user = Auth::guard('web')->user();
        if (! $user instanceof User || ! $user->isBookingClient()) {
            throw ValidationException::withMessages([
                'login' => [__('booking.validation.registration_session_expired')],
            ]);
        }

        $deviceName = trim((string) $request->input('device_name', ''));
        $issued = ClientApiToken::issue($user, (int) $store->id, $deviceName !== '' ? $deviceName : 'App');

        return response()->json([
            'token' => $issued['plain'],
            'is_new_account' => $isNew,
            'customer' => ClientApiPresenter::customer($user->fresh('client')),
        ]);
    }
}
