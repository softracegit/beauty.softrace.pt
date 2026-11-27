<?php

namespace App\Http\Middleware;

use App\Models\ClientApiToken;
use App\Models\Store;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * API da app: exige "Authorization: Bearer {id}|{segredo}" de um cliente de marcação
 * da mesma organização da loja do URL. Define o utilizador para o resto do pedido.
 */
class AuthenticateClientApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = (string) $request->bearerToken();
        $token = $plain !== '' ? ClientApiToken::findValid($plain) : null;
        $user = $token?->user;

        if (! $token || ! $user instanceof User || ! $user->isBookingClient()) {
            return response()->json(['message' => 'Sessão terminada. Entre novamente.'], 401);
        }

        $store = $request->route('store');
        if ($store instanceof Store) {
            $client = $user->loadMissing('client')->client;
            if (! $client || ! $client->belongsToOrganizationOfStore($store)) {
                return response()->json(['message' => 'Esta conta não pertence a esta loja.'], 403);
            }
        }

        $token->touchUsage();
        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('client_api_token', $token);

        return $next($request);
    }
}
