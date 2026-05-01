<?php

namespace App\Http\Controllers;

use App\Mail\BookingAuthCodeMail;
use App\Models\BookingAuthCode;
use App\Models\Client;
use App\Models\User;
use App\Services\TwilioSmsService;
use App\Support\PhoneDisplay;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingClientAuthController extends Controller
{
    private const PENDING_REG_CHANNEL_KEY = 'booking_auth.pending_registration.channel';

    private const PENDING_REG_IDENTIFIER_KEY = 'booking_auth.pending_registration.identifier';

    public function __construct(
        private readonly TwilioSmsService $twilioSmsService
    ) {}

    public function requestCodeFromAuthModal(Request $request): JsonResponse
    {
        $target = $this->resolveAuthTargetFromRequest($request, validateOnly: false);
        $user = $target['channel'] === 'email'
            ? $this->resolveBookingClientUserForEmail($target['identifier'])
            : $this->resolveBookingClientUserForPhone($target['identifier']);
        $ttlMinutes = max(3, (int) config('booking.auth_code_ttl_minutes', 10));
        $code = (string) random_int(100000, 999999);

        BookingAuthCode::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($target['identifier'])])
            ->whereNull('consumed_at')
            ->delete();

        BookingAuthCode::query()->create([
            // Coluna "email" é usada como identificador (email ou telemóvel E.164).
            'email' => $target['identifier'],
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addMinutes($ttlMinutes),
            'requested_ip' => $request->ip(),
            'requested_user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
        ]);

        try {
            if ($target['channel'] === 'email') {
                Mail::mailer('booking')->to($target['identifier'])->send(new BookingAuthCodeMail($code, $ttlMinutes));
            } else {
                $this->twilioSmsService->send(
                    $target['identifier'],
                    sprintf('O seu código de acesso é %s. Expira em %d minutos.', $code, $ttlMinutes)
                );
            }
        } catch (\Throwable $e) {
            Log::error('Envio do código de acesso (booking) falhou.', [
                'channel' => $target['channel'],
                'target' => $target['identifier'],
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            throw ValidationException::withMessages([
                'login' => ['Não foi possível enviar o código agora. Tente novamente em instantes.'],
            ]);
        }

        return response()->json([
            'ok' => true,
            'channel' => $target['channel'],
            'identifier' => $target['identifier'],
            'expires_in' => $ttlMinutes * 60,
            'known_account' => $user instanceof User,
        ]);
    }

    public function verifyCodeFromAuthModal(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'digits:6']]);
        $target = $this->resolveAuthTargetFromRequest($request, validateOnly: false);
        $code = trim((string) $validated['code']);
        $resolved = $target['channel'] === 'email'
            ? $this->resolveBookingClientUserForEmail($target['identifier'])
            : $this->resolveBookingClientUserForPhone($target['identifier']);

        $authCode = BookingAuthCode::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($target['identifier'])])
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        // Compatibilidade temporária com códigos emitidos antes da unificação.
        if (! $authCode && $resolved instanceof User && $target['channel'] === 'email') {
            $emailInput = strtolower($target['identifier']);
            $resolvedUserEmail = strtolower(trim((string) $resolved->email));
            if ($resolvedUserEmail !== '' && $resolvedUserEmail !== $emailInput) {
                $authCode = BookingAuthCode::query()
                    ->whereRaw('LOWER(email) = ?', [$resolvedUserEmail])
                    ->whereNull('consumed_at')
                    ->where('expires_at', '>', now())
                    ->latest('id')
                    ->first();
            }
        }

        if (! $authCode) {
            throw ValidationException::withMessages([
                'code' => ['Código inválido ou expirado. Solicite um novo código.'],
            ]);
        }

        $authCode->attempts = (int) $authCode->attempts + 1;
        $isValid = hash_equals((string) $authCode->code_hash, hash('sha256', $code));
        if (! $isValid) {
            $authCode->save();
            throw ValidationException::withMessages([
                'code' => ['Código inválido. Verifique e tente novamente.'],
            ]);
        }
        $authCode->consumed_at = now();
        $authCode->save();

        if (! $resolved instanceof User) {
            $request->session()->put(self::PENDING_REG_CHANNEL_KEY, $target['channel']);
            $request->session()->put(self::PENDING_REG_IDENTIFIER_KEY, $target['identifier']);

            return response()->json([
                'ok' => true,
                'requires_registration' => true,
                'channel' => $target['channel'],
                'identifier' => $target['identifier'],
            ]);
        }

        $user = $resolved;
        $this->clearPendingRegistration($request);

        if ($target['channel'] === 'email') {
            $this->syncBookingUserEmailAfterVerifiedCode($user, $target['identifier']);
        } else {
            $this->syncBookingUserPhoneAfterVerifiedCode($user, $target['identifier']);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json([
            'ok' => true,
            'is_new_account' => false,
        ]);
    }

    public function completeRegistrationFromAuthModal(Request $request): JsonResponse
    {
        $channel = (string) $request->session()->get(self::PENDING_REG_CHANNEL_KEY, '');
        $identifier = (string) $request->session()->get(self::PENDING_REG_IDENTIFIER_KEY, '');
        if ($channel === '' || $identifier === '') {
            throw ValidationException::withMessages([
                'login' => ['A sessão de registo expirou. Peça um novo código.'],
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        $name = trim((string) $validated['name']);
        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => ['Indique o nome.'],
            ]);
        }

        if ($channel === 'phone') {
            $emailNorm = strtolower(trim((string) ($validated['email'] ?? '')));
            if ($emailNorm === '') {
                throw ValidationException::withMessages([
                    'email' => ['Indique o email para criar a conta.'],
                ]);
            }
            $phoneE164 = trim((string) $identifier);
        } else {
            $emailNorm = strtolower(trim((string) $identifier));
            $phoneE164 = PhoneDisplay::toE164((string) ($validated['phone'] ?? ''));
            if ($phoneE164 === null) {
                throw ValidationException::withMessages([
                    'phone' => ['Indique um telemóvel válido para criar a conta.'],
                ]);
            }
        }

        $existingClient = $this->findLegacyClientForPendingBookingRegistration($channel, $phoneE164, $emailNorm);

        if ($existingClient instanceof Client) {
            if ($this->bookingUserExistsForClient($existingClient)) {
                Log::warning('Registo booking: cliente já tem utilizador de marcação.', [
                    'client_id' => $existingClient->id,
                ]);
                throw ValidationException::withMessages([
                    'login' => ['Esta conta já existe. Inicie sessão com o código enviado.'],
                ]);
            }

            $this->assertEmailAvailableForBookingRegistration($emailNorm, $existingClient);
            $this->assertPhoneAvailableForBookingRegistration($phoneE164, $existingClient);

            if ($channel === 'phone') {
                $clientEmail = strtolower(trim((string) ($existingClient->email ?? '')));
                if ($clientEmail !== '' && $clientEmail !== $emailNorm) {
                    throw ValidationException::withMessages([
                        'email' => ['Este telemóvel já está associado a outro email na loja. Use o contacto habitual ou fale com a loja.'],
                    ]);
                }
            } else {
                $rawPhone = trim((string) ($existingClient->phone ?? ''));
                if ($rawPhone !== '' && ! $this->clientPhoneMatchesE164($existingClient, $phoneE164)) {
                    throw ValidationException::withMessages([
                        'phone' => ['Este telemóvel não coincide com o email na nossa base de dados.'],
                    ]);
                }
            }

            $existingClient->name = $name;
            $existingClient->phone = $phoneE164;
            if (trim((string) ($existingClient->email ?? '')) === '') {
                $existingClient->email = $emailNorm;
            }

            try {
                $existingClient->save();
            } catch (QueryException $e) {
                $this->throwFriendlyDuplicateEntryIfApplicable($e);
                throw $e;
            }

            $client = $existingClient->fresh();
        } else {
            $this->assertEmailAvailableForBookingRegistration($emailNorm, null);
            $this->assertPhoneAvailableForBookingRegistration($phoneE164, null);

            try {
                $client = Client::query()->create([
                    'name' => $name,
                    'email' => $emailNorm,
                    'phone' => $phoneE164,
                    'type' => Client::TYPE_POTENCIAL_CLIENTE,
                ]);
            } catch (QueryException $e) {
                $this->throwFriendlyDuplicateEntryIfApplicable($e);
                throw $e;
            }
        }

        try {
            $user = $this->createBookingUserForClient($client, $emailNorm);
        } catch (QueryException $e) {
            $this->throwFriendlyDuplicateEntryIfApplicable($e);
            throw $e;
        }
        $this->clearPendingRegistration($request);

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json([
            'ok' => true,
            'is_new_account' => true,
        ]);
    }

    /**
     * @return array{channel: 'email'|'phone', identifier: string}
     */
    private function resolveAuthTargetFromRequest(Request $request, bool $validateOnly): array
    {
        $request->validate([
            'login' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        $rawLogin = trim((string) ($request->input('login') ?? ''));
        if ($rawLogin === '') {
            $rawLogin = trim((string) ($request->input('email') ?? ''));
        }
        if ($rawLogin === '') {
            $rawLogin = trim((string) ($request->input('phone') ?? ''));
        }

        if ($rawLogin === '') {
            throw ValidationException::withMessages([
                'login' => ['Indique o email ou telemóvel.'],
            ]);
        }

        if (str_contains($rawLogin, '@')) {
            $email = strtolower($rawLogin);
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages([
                    'login' => ['Indique um email válido.'],
                ]);
            }

            return ['channel' => 'email', 'identifier' => $email];
        }

        $phone = PhoneDisplay::toE164($rawLogin);
        if ($phone === null) {
            throw ValidationException::withMessages([
                'login' => ['Indique um telemóvel válido (ex.: +351912345678).'],
            ]);
        }

        if (! $validateOnly) {
            $this->assertNoPhoneConflictForBookingAuth($phone);
        }

        return ['channel' => 'phone', 'identifier' => $phone];
    }

    private function createBookingUserForClient(Client $client, string $userEmail): User
    {
        $displayName = trim((string) $client->name) !== '' ? (string) $client->name : 'Cliente';

        $user = User::query()->create([
            'name' => $displayName,
            'email' => $userEmail,
            'password' => Hash::make(Str::random(64)),
            'role' => User::ROLE_CLIENTE,
            'client_id' => $client->id,
            'must_set_password' => false,
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    /**
     * Conta de marcação online: email em `users.email` ou na ficha `clients.email` (dados legados / CRM).
     */
    private function resolveBookingClientUserForEmail(string $emailNorm): ?User
    {
        if ($emailNorm === '') {
            return null;
        }

        $byUserEmail = User::query()
            ->whereRaw('LOWER(email) = ?', [$emailNorm])
            ->where('role', User::ROLE_CLIENTE)
            ->first();

        if ($byUserEmail instanceof User) {
            return $byUserEmail;
        }

        return User::query()
            ->where('role', User::ROLE_CLIENTE)
            ->whereNotNull('client_id')
            ->whereHas('client', function ($q) use ($emailNorm): void {
                $q->whereNotNull('email')
                    ->where('email', '!=', '')
                    ->whereRaw('LOWER(TRIM(email)) = ?', [$emailNorm]);
            })
            ->first();
    }

    private function resolveBookingClientUserForPhone(string $phoneE164): ?User
    {
        $phoneE164 = trim($phoneE164);
        if ($phoneE164 === '') {
            return null;
        }

        $clientIds = Client::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get(['id', 'phone'])
            ->filter(function (Client $client) use ($phoneE164): bool {
                return PhoneDisplay::toE164((string) $client->phone) === $phoneE164;
            })
            ->pluck('id')
            ->values();

        if ($clientIds->isEmpty()) {
            return null;
        }
        if ($clientIds->count() > 1) {
            throw ValidationException::withMessages([
                'login' => ['Existe mais do que um cliente com este telemóvel. Contacte o suporte para unificar os registos.'],
            ]);
        }

        return User::query()
            ->where('role', User::ROLE_CLIENTE)
            ->where('client_id', (int) $clientIds->first())
            ->first();
    }

    private function assertNoEmailConflictForBookingAuth(string $emailNorm): void
    {
        $emailNorm = strtolower(trim($emailNorm));
        $matchingUsers = User::query()
            ->whereRaw('LOWER(email) = ?', [$emailNorm])
            ->get();
        if ($matchingUsers->count() > 1) {
            throw ValidationException::withMessages([
                'login' => ['Este email está duplicado em utilizadores. Contacte o suporte.'],
            ]);
        }
        $single = $matchingUsers->first();
        if ($single instanceof User && ! $single->isBookingClient()) {
            throw ValidationException::withMessages([
                'login' => ['Este email já está associado a outro tipo de conta. Use outro email.'],
            ]);
        }

        $clients = Client::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereRaw('LOWER(TRIM(email)) = ?', [$emailNorm])
            ->count();
        if ($clients > 1) {
            throw ValidationException::withMessages([
                'login' => ['Existe mais do que um cliente com este email. Contacte o suporte para unificar os registos.'],
            ]);
        }
    }

    private function assertNoPhoneConflictForBookingAuth(string $phoneE164): void
    {
        $matches = Client::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get(['id', 'phone'])
            ->filter(fn (Client $client): bool => PhoneDisplay::toE164((string) $client->phone) === $phoneE164)
            ->values();

        if ($matches->count() > 1) {
            throw ValidationException::withMessages([
                'login' => ['Existe mais do que um cliente com este telemóvel. Contacte o suporte para unificar os registos.'],
            ]);
        }
    }

    private function findLegacyClientForPendingBookingRegistration(string $channel, string $phoneE164, string $emailNorm): ?Client
    {
        $phoneE164 = trim($phoneE164);
        if ($channel === 'phone') {
            $matches = Client::query()
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->get(['id', 'phone'])
                ->filter(fn (Client $c): bool => PhoneDisplay::toE164((string) $c->phone) === $phoneE164)
                ->values();

            if ($matches->count() > 1) {
                throw ValidationException::withMessages([
                    'login' => ['Existe mais do que um cliente com este telemóvel. Contacte o suporte para unificar os registos.'],
                ]);
            }

            $first = $matches->first();
            if (! $first instanceof Client) {
                return null;
            }

            // `get(['id','phone'])` não carrega `email`; sem `fresh()` a validação de conflito falha em silêncio.
            return $first->fresh();
        }

        $emailNorm = strtolower(trim($emailNorm));
        if ($emailNorm === '') {
            return null;
        }

        return Client::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereRaw('LOWER(TRIM(email)) = ?', [$emailNorm])
            ->first();
    }

    private function bookingUserExistsForClient(Client $client): bool
    {
        return User::query()
            ->where('role', User::ROLE_CLIENTE)
            ->where('client_id', $client->id)
            ->exists();
    }

    private function assertEmailAvailableForBookingRegistration(string $emailNorm, ?Client $except): void
    {
        $emailNorm = strtolower(trim($emailNorm));
        if ($emailNorm === '') {
            throw ValidationException::withMessages([
                'email' => ['Indique um email válido.'],
            ]);
        }

        if (User::query()->whereRaw('LOWER(email) = ?', [$emailNorm])->exists()) {
            throw ValidationException::withMessages([
                'email' => ['Este email já está associado a uma conta. Use outro email ou inicie sessão.'],
            ]);
        }

        $q = Client::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereRaw('LOWER(TRIM(email)) = ?', [$emailNorm]);

        if ($except instanceof Client) {
            $q->where('id', '!=', $except->id);
        }

        if ($q->exists()) {
            throw ValidationException::withMessages([
                'email' => ['Este email já está associado a um cliente. Use outro email ou inicie sessão.'],
            ]);
        }
    }

    private function assertPhoneAvailableForBookingRegistration(string $phoneE164, ?Client $except): void
    {
        $phoneE164 = trim($phoneE164);
        if ($phoneE164 === '') {
            throw ValidationException::withMessages([
                'phone' => ['Indique um telemóvel válido.'],
            ]);
        }

        $conflict = Client::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->when($except instanceof Client, fn ($q) => $q->where('id', '!=', $except->id))
            ->get(['id', 'phone'])
            ->contains(fn (Client $c): bool => PhoneDisplay::toE164((string) $c->phone) === $phoneE164);

        if ($conflict) {
            throw ValidationException::withMessages([
                'phone' => ['Este telemóvel já está associado a outro cliente. Indique outro número ou inicie sessão.'],
            ]);
        }
    }

    private function clientPhoneMatchesE164(Client $client, string $phoneE164): bool
    {
        $raw = trim((string) ($client->phone ?? ''));
        if ($raw === '') {
            return false;
        }

        $ex = PhoneDisplay::toE164($raw);
        $n = PhoneDisplay::toE164($phoneE164);
        if ($ex !== null && $n !== null) {
            return $ex === $n;
        }

        return $raw === trim($phoneE164);
    }

    private function throwFriendlyDuplicateEntryIfApplicable(QueryException $e): void
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (int) ($e->errorInfo[1] ?? 0);
        if ($sqlState === '23000' && $driverCode === 1062) {
            Log::notice('Conflito de unicidade na BD ao registar conta de booking; resposta genérica ao utilizador.', [
                'exception' => $e::class,
            ]);
            throw ValidationException::withMessages([
                'login' => ['Não foi possível concluir o registo. Se já tiver conta, inicie sessão; caso contrário, contacte a loja.'],
            ]);
        }
    }

    private function clearPendingRegistration(Request $request): void
    {
        $request->session()->forget([
            self::PENDING_REG_CHANNEL_KEY,
            self::PENDING_REG_IDENTIFIER_KEY,
        ]);
    }

    /**
     * Após validação do código, mantém users.email alinhado com o email verificado no booking
     * quando a conta cliente foi resolvida por clients.email (dados legados).
     */
    private function syncBookingUserEmailAfterVerifiedCode(User $user, string $verifiedEmail): void
    {
        $verifiedEmail = strtolower(trim($verifiedEmail));
        if ($verifiedEmail === '' || ! $user->isBookingClient()) {
            return;
        }

        $currentUserEmail = strtolower(trim((string) $user->email));
        if ($currentUserEmail === $verifiedEmail) {
            return;
        }

        $clientEmail = strtolower(trim((string) ($user->client?->email ?? '')));
        if ($clientEmail !== $verifiedEmail) {
            return;
        }

        $emailConflict = User::query()
            ->whereRaw('LOWER(email) = ?', [$verifiedEmail])
            ->where('id', '!=', $user->id)
            ->exists();

        if ($emailConflict) {
            Log::warning('Sincronização users.email ignorada por conflito.', [
                'user_id' => $user->id,
                'client_id' => $user->client_id,
                'verified_email' => $verifiedEmail,
            ]);

            return;
        }

        $user->forceFill([
            'email' => $verifiedEmail,
            'email_verified_at' => now(),
        ])->save();
    }

    private function syncBookingUserPhoneAfterVerifiedCode(User $user, string $verifiedPhoneE164): void
    {
        $verifiedPhoneE164 = trim($verifiedPhoneE164);
        if ($verifiedPhoneE164 === '' || ! $user->isBookingClient() || ! ($user->client instanceof Client)) {
            return;
        }

        $currentPhone = PhoneDisplay::toE164((string) ($user->client->phone ?? ''));
        if ($currentPhone === $verifiedPhoneE164) {
            return;
        }

        $conflict = Client::query()
            ->where('id', '!=', $user->client->id)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get(['id', 'phone'])
            ->contains(fn (Client $client): bool => PhoneDisplay::toE164((string) $client->phone) === $verifiedPhoneE164);

        if ($conflict) {
            Log::warning('Sincronização clients.phone ignorada por conflito.', [
                'user_id' => $user->id,
                'client_id' => $user->client->id,
                'verified_phone' => $verifiedPhoneE164,
            ]);

            return;
        }

        $user->client->forceFill(['phone' => $verifiedPhoneE164])->save();
    }
}
