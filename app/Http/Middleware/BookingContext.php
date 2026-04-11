<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contexto do fluxo público de marcação (booking).
 * Ponto único para futuras regras: throttling, feature flag, locale, etc.
 */
class BookingContext
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
