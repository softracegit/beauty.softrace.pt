<?php

use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
            'booking' => \App\Http\Middleware\BookingContext::class,
            'booking.client' => \App\Http\Middleware\EnsureUserIsBookingClient::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('booking/conta*')) {
                return route('booking.acesso');
            }

            return route('login');
        });

        $middleware->redirectUsersTo(function (Request $request) {
            $user = $request->user();
            if ($user instanceof User && $user->isBookingClient()) {
                return route('booking.index');
            }

            return route('dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
