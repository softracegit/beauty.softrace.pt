<?php

namespace App\Http\Controllers;

use App\Mail\BookingAuthCodeMail;
use App\Models\Client;
use App\Models\BookingAuthCode;
use App\Models\User;
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
    public function requestCodeFromAuthModal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($validated['email']));
        $user = $this->resolveBookingClientUserForEmail($email);
        // O código deve ser enviado para o email introduzido no booking.
        // Isto evita conflitos em contas legadas onde users.email != clients.email.
        $targetEmail = $email;
        $ttlMinutes = max(3, (int) config('booking.auth_code_ttl_minutes', 10));
        $code = (string) random_int(100000, 999999);

        BookingAuthCode::query()
            ->whereRaw('LOWER(email) = ?', [$targetEmail])
            ->whereNull('consumed_at')
            ->delete();

        BookingAuthCode::query()->create([
            'email' => $targetEmail,
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addMinutes($ttlMinutes),
            'requested_ip' => $request->ip(),
            'requested_user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
        ]);

        try {
            Mail::mailer('booking')->to($targetEmail)->send(new BookingAuthCodeMail($code, $ttlMinutes));
        } catch (\Throwable $e) {
            Log::error('Envio do código de acesso (booking) falhou.', [
                'email' => $targetEmail,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            throw ValidationException::withMessages([
                'email' => ['Não foi possível enviar o código agora. Tente novamente em instantes.'],
            ]);
        }

        return response()->json([
            'ok' => true,
            'email' => $targetEmail,
            'expires_in' => $ttlMinutes * 60,
        ]);
    }

    public function verifyCodeFromAuthModal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ]);

        $emailInput = strtolower(trim((string) $validated['email']));
        $code = trim((string) $validated['code']);
        $resolved = $this->resolveBookingClientUserForEmail($emailInput);

        $authCode = BookingAuthCode::query()
            ->whereRaw('LOWER(email) = ?', [$emailInput])
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        // Compatibilidade temporária: permite validar códigos emitidos antes desta alteração
        // quando o código foi enviado para users.email em vez do email introduzido.
        if (! $authCode && $resolved instanceof User) {
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

        $user = $resolved ?: $this->createPasswordlessBookingUserFromEmail($emailInput);
        $this->syncBookingUserEmailAfterVerifiedCode($user, $emailInput);
        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json([
            'ok' => true,
            'is_new_account' => ! $resolved,
        ]);
    }

    private function createPasswordlessBookingUserFromEmail(string $emailNorm): User
    {
        $emailNorm = strtolower(trim($emailNorm));
        if ($emailNorm === '') {
            throw ValidationException::withMessages([
                'email' => ['Email inválido para criar conta.'],
            ]);
        }

        $existing = User::query()->whereRaw('LOWER(email) = ?', [$emailNorm])->first();
        if ($existing instanceof User) {
            if (! $existing->isBookingClient()) {
                throw ValidationException::withMessages([
                    'email' => ['Este email já está associado a outro tipo de conta. Use outro email.'],
                ]);
            }

            return $existing;
        }

        $localPart = explode('@', $emailNorm)[0] ?? 'cliente';
        $baseName = trim((string) preg_replace('/[\._\-]+/', ' ', $localPart));
        $displayName = Str::title($baseName !== '' ? $baseName : 'Cliente');

        $client = Client::query()->create([
            'name' => $displayName,
            'email' => $emailNorm,
            'phone' => null,
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);

        $user = User::query()->create([
            'name' => $displayName,
            'email' => $emailNorm,
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
}
