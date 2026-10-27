<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Show the login form.
     */
    public function showLoginForm()
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof User && $user->isBookingClient()) {
                return redirect()->route('booking.index', [
                    'store' => $user->bookingPublicHomeStoreSlug(),
                ]);
            }
            if ($user instanceof User && $user->isSuperAdmin()) {
                return redirect()->route('super-admin.dashboard');
            }

            return redirect()->route('dashboard');
        }

        return view('auth-signin');
    }

    /**
     * Handle login request.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $credentials = $request->only('email', 'password');
        $remember = $request->filled('remember');

        if (Auth::attempt($credentials, $remember)) {
            $user = Auth::user();
            if ($user instanceof User && $user->isBookingClient()) {
                Auth::logout();
                $request->session()->regenerate();

                throw ValidationException::withMessages([
                    'email' => ['Contas de marcação online iniciam sessão na marcação (botão «Iniciar sessão»): '.route('booking.index', [
                        'store' => $user->bookingPublicHomeStoreSlug(),
                        'open_auth' => '1',
                    ]).'.'],
                ]);
            }

            $request->session()->regenerate();

            if ($user instanceof User && $user->isSuperAdmin()) {
                return redirect()->intended(route('super-admin.dashboard'));
            }

            return redirect()->intended(route('dashboard'));
        }

        throw ValidationException::withMessages([
            'email' => ['As credenciais fornecidas estão incorretas.'],
        ]);
    }

    /**
     * Show the registration form.
     */
    public function showRegisterForm()
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof User && $user->isBookingClient()) {
                return redirect()->route('booking.index', [
                    'store' => $user->bookingPublicHomeStoreSlug(),
                ]);
            }
            if ($user instanceof User && $user->isSuperAdmin()) {
                return redirect()->route('super-admin.dashboard');
            }

            return redirect()->route('dashboard');
        }

        return view('auth-signup');
    }

    /**
     * Handle registration request.
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        Auth::login($user);

        return redirect()->route('dashboard');
    }

    /**
     * Show the forgot password form.
     */
    public function showForgotPasswordForm()
    {
        return view('auth-forgot-password');
    }

    /**
     * Send the password reset link email.
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', 'Enviámos um link de redefinição para o seu email.');
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

    /**
     * Show the reset password form.
     */
    public function showResetPasswordForm(Request $request, string $token)
    {
        return view('auth-reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    /**
     * Handle password reset.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                    'must_set_password' => false,
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()
                ->route('login')
                ->with('status', 'Palavra-passe redefinida. Já pode iniciar sessão.');
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

    /**
     * Handle logout request.
     */
    public function logout(Request $request)
    {
        $user = Auth::user();
        $wasBookingClient = $user instanceof User && $user->isBookingClient();
        $bookingStoreSlug = $wasBookingClient && $user instanceof User
            ? $user->bookingPublicHomeStoreSlug()
            : null;

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($wasBookingClient && $bookingStoreSlug !== null) {
            return redirect()->route('booking.index', ['store' => $bookingStoreSlug]);
        }

        return redirect()->route('login');
    }
}
