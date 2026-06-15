<?php

use App\Models\User;
use App\Support\FriendlyErrorMessages;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Aplicar middleware para garantir que apenas users com agent podem aceder
        $middleware->alias([
            'has.agent' => \App\Http\Middleware\EnsureUserHasAgent::class,
            'set.current.store' => \App\Http\Middleware\SetCurrentStore::class,
            'cash.register.open' => \App\Http\Middleware\EnsureCashRegisterOpen::class,
            'backoffice.access' => \App\Http\Middleware\EnsureBackofficeAccess::class,
            'super.admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
            'booking' => \App\Http\Middleware\BookingContext::class,
            'booking.locale' => \App\Http\Middleware\SetBookingLocale::class,
            'booking.client' => \App\Http\Middleware\EnsureUserIsBookingClient::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            if (preg_match('#^booking/([^/]+)/conta#', $request->path(), $m)) {
                return route('booking.index', [
                    'store' => $m[1],
                    'open_auth' => '1',
                ]);
            }

            return route('login');
        });

        $middleware->redirectUsersTo(function (Request $request) {
            $user = $request->user();
            if ($user instanceof User && $user->isPrestador()) {
                return route('agenda.index');
            }
            if ($user instanceof User && $user->isBookingClient()) {
                return route('booking.index', [
                    'store' => $user->bookingPublicHomeStoreSlug(),
                ]);
            }
            if ($user instanceof User && $user->isSuperAdmin()) {
                return route('super-admin.dashboard');
            }

            return route('dashboard');
        });

        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            $message = FriendlyErrorMessages::CSRF_SESSION_EXPIRED;

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 419);
            }

            return redirect()
                ->back()
                ->withInput($request->except('_token'))
                ->withErrors(['_token' => $message]);
        });
    })->create();
