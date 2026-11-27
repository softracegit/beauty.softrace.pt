<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A entrada com código da app reutiliza o BookingClientAuthController do booking web,
 * que escreve na sessão. Na API não há sessão nem cookies: este middleware dá ao pedido
 * uma sessão em memória (driver "array") que nunca é gravada nem enviada.
 */
class UseArraySessionForApi
{
    public function handle(Request $request, Closure $next): Response
    {
        config(['session.driver' => 'array']);
        $session = app('session')->driver('array');
        $session->start();

        app()->instance('session.store', $session);
        $request->setLaravelSession($session);
        Auth::forgetGuards();

        return $next($request);
    }
}
