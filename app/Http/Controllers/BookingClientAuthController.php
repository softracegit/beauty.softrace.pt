<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\BookingMagicLoginToken;
use App\Models\User;
use App\Support\PhoneDisplay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class BookingClientAuthController extends Controller
{
    public function showAcesso(Request $request): View
    {
        return view('booking.acesso', [
            'businessName' => config('app.name'),
            'email' => old('email', (string) $request->query('email', '')),
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    public function sendMagicLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($validated['email']));
        $user = $this->resolveBookingClientUserForEmail($email);

        if ($user) {
            BookingMagicLoginToken::sendFreshLink($user);
        }

        return back()->with('status', 'Se existir uma conta com este email, foi enviado um email de recuperação de password.');
    }

    public function checkEmailForAuthModal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($validated['email']));
        $user = $this->resolveBookingClientUserForEmail($email);

        return response()->json([
            'exists' => (bool) $user,
            'email' => $email,
            'login_email' => $user ? strtolower(trim((string) $user->email)) : '',
        ]);
    }

    public function loginFromAuthModal(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $emailInput = strtolower(trim((string) $request->input('email')));
        $resolved = $this->resolveBookingClientUserForEmail($emailInput);
        $emailForAttempt = $resolved instanceof User
            ? strtolower(trim((string) $resolved->email))
            : $emailInput;

        $credentials = [
            'email' => $emailForAttempt,
            'password' => (string) $request->input('password'),
        ];

        if (! Auth::attempt($credentials, true)) {
            throw ValidationException::withMessages([
                'password' => ['Password incorreta. Tenta novamente.'],
            ]);
        }

        $user = Auth::user();
        if (! $user instanceof User || ! $user->isBookingClient()) {
            Auth::logout();
            $request->session()->regenerate();

            throw ValidationException::withMessages([
                'email' => ['Esta conta não é válida para a marcação online.'],
            ]);
        }

        $request->session()->regenerate();

        return response()->json([
            'ok' => true,
        ]);
    }

    public function registerFromAuthModal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'password' => ['required', 'string', 'min:8', 'regex:/\d/'],
            'privacy' => ['accepted'],
            'terms' => ['accepted'],
        ], [
            'password.regex' => 'A password deve incluir pelo menos um número.',
        ]);

        $emailNorm = strtolower(trim($validated['email']));
        $phoneE164 = PhoneDisplay::toE164(trim($validated['phone']));
        if ($phoneE164 === null || $phoneE164 === '') {
            throw ValidationException::withMessages([
                'phone' => ['Telemóvel inválido para o país selecionado.'],
            ]);
        }

        if ($this->resolveBookingClientUserForEmail($emailNorm)) {
            throw ValidationException::withMessages([
                'email' => ['Este email já está associado a uma conta. Inicia sessão.'],
            ]);
        }

        if (User::query()->whereRaw('LOWER(email) = ?', [$emailNorm])->exists()) {
            throw ValidationException::withMessages([
                'email' => ['Este email já está associado a uma conta. Inicia sessão.'],
            ]);
        }

        if (Client::existsWithSamePhoneAs($phoneE164)) {
            throw ValidationException::withMessages([
                'phone' => ['Este telemóvel já está associado a uma conta existente. Inicia sessão com o email.'],
            ]);
        }

        $client = Client::query()->create([
            'name' => $validated['name'],
            'email' => $emailNorm,
            'phone' => $phoneE164,
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $emailNorm,
            'password' => Hash::make($validated['password']),
            'role' => User::ROLE_CLIENTE,
            'client_id' => $client->id,
            'must_set_password' => false,
        ]);

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json([
            'ok' => true,
        ]);
    }

    public function sendPasswordSetupLinkFromAuthModal(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($validated['email']));
        $user = $this->resolveBookingClientUserForEmail($email);

        if ($user) {
            BookingMagicLoginToken::sendFreshLink($user, 'password');
        }

        $message = 'Se o email existir, enviámos um link seguro para definires uma nova password.';
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
            ]);
        }

        return back()->with('status', $message);
    }

    public function consumeMagicLink(Request $request, string $token): RedirectResponse
    {
        $token = trim($token);
        if ($token === '' || strlen($token) > 128) {
            return redirect()->route('booking.acesso')
                ->with('error', 'Link inválido.');
        }

        $record = BookingMagicLoginToken::query()
            ->where('token', $token)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $record) {
            return redirect()->route('booking.acesso')
                ->with('error', 'Este link expirou ou já foi utilizado. Pede um novo link.');
        }

        $user = User::query()->find($record->user_id);
        if (! $user || ! $user->isBookingClient()) {
            return redirect()->route('booking.acesso')
                ->with('error', 'Conta inválida.');
        }

        $record->used_at = now();
        $record->save();

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->route('booking.step3');
    }

    public function showPasswordResetForm(Request $request, string $token): View|RedirectResponse
    {
        $token = trim($token);
        if ($token === '' || strlen($token) > 128) {
            return redirect()->route('booking.index', ['open_auth' => '1'])
                ->with('error', 'Link de recuperação inválido.');
        }

        $record = BookingMagicLoginToken::query()
            ->where('token', $token)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $record) {
            return redirect()->route('booking.index', ['open_auth' => '1'])
                ->with('error', 'Este link de recuperação expirou ou já foi utilizado.');
        }

        $user = User::query()->find($record->user_id);
        if (! $user || ! $user->isBookingClient()) {
            return redirect()->route('booking.index', ['open_auth' => '1'])
                ->with('error', 'Conta inválida para recuperação de password.');
        }

        return view('booking.password-reset', [
            'businessName' => config('app.name'),
            'email' => (string) $user->email,
            'token' => $token,
        ]);
    }

    public function resetPasswordWithToken(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:128'],
            'password' => ['required', 'confirmed', Password::min(8)->numbers()],
        ], [
            'password.confirmed' => 'A confirmação da password não coincide.',
        ]);

        $token = trim((string) $validated['token']);
        $record = BookingMagicLoginToken::query()
            ->where('token', $token)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $record) {
            return redirect()->route('booking.index', ['open_auth' => '1'])
                ->with('error', 'Este link de recuperação expirou ou já foi utilizado.');
        }

        $user = User::query()->find($record->user_id);
        if (! $user || ! $user->isBookingClient()) {
            return redirect()->route('booking.index', ['open_auth' => '1'])
                ->with('error', 'Conta inválida para recuperação de password.');
        }

        $user->password = Hash::make((string) $validated['password']);
        $user->must_set_password = false;
        $user->save();

        $record->used_at = now();
        $record->save();

        return redirect()->route('booking.index', [
            'open_auth' => '1',
            'email' => $user->email,
        ])->with('status', 'Password atualizada com sucesso. Inicia sessão com a nova password.');
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
}
