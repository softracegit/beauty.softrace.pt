<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasAgent
{
    /**
     * Handle an incoming request.
     * Garante que apenas utilizadores com agent associado podem aceder ao sistema.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user instanceof User && $user->isBookingClient()) {
            return redirect()->route('booking.index');
        }

        // Se o user não tem agent associado, redirecionar para uma página de erro ou logout
        if (! $user->agent) {
            auth()->logout();

            return redirect()->route('login')
                ->with('error', 'A sua conta não está associada a um agente. Contacte o administrador.');
        }
        
        return $next($request);
    }
}
