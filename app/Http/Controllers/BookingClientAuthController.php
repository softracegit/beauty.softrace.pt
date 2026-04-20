<?php

namespace App\Http\Controllers;

use App\Models\BookingMagicLoginToken;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
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

    public function showPasswordLogin(Request $request): View
    {
        return view('booking.login', [
            'businessName' => config('app.name'),
            'email' => old('email', (string) $request->query('email', '')),
        ]);
    }

    public function loginWithPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => ['As credenciais fornecidas estão incorretas.'],
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();
        if (! $user instanceof User || ! $user->isBookingClient()) {
            Auth::logout();
            $request->session()->regenerate();

            throw ValidationException::withMessages([
                'email' => ['Esta página é só para a tua conta de marcação online. Para o acesso da equipa (CRM), usa o login interno.'],
            ]);
        }

        return redirect()->intended(route('booking.index'));
    }

    public function sendMagicLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($validated['email']));
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('role', User::ROLE_CLIENTE)
            ->first();

        if ($user) {
            BookingMagicLoginToken::sendFreshLink($user);
        }

        return back()->with('status', 'Se existir uma conta com este email, enviámos um link de acesso. Verifica a caixa de entrada e o spam.');
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
}
