<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
