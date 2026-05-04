<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Models\User;
use App\Support\CurrentStore;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentStore
{
    public const SESSION_KEY = 'current_store_id';

    public function __construct(
        private readonly CurrentStore $currentStore
    ) {}

    /**
     * Resolve a loja activa (sessão + permissões), sem depender de {@see CurrentStore}.
     * Usado também no route model binding: `SubstituteBindings` corre antes deste middleware.
     */
    public static function resolveActiveStore(User $user, Request $request): Store
    {
        $user->loadMissing(['agent.store']);

        $agent = $user->agent;
        $homeStore = $agent?->store;
        if ($homeStore === null) {
            abort(503, 'A sua conta não está associada a uma loja. Contacte o administrador.');
        }

        $accessible = $user->accessibleStores();
        $accessibleIds = $accessible->pluck('id')->map(fn ($id) => (int) $id)->all();

        $sessionId = $request->session()->get(self::SESSION_KEY);
        $sessionId = is_numeric($sessionId) ? (int) $sessionId : null;

        $resolved = $homeStore;
        if ($sessionId !== null && in_array($sessionId, $accessibleIds, true)) {
            $resolved = $accessible->firstWhere('id', $sessionId) ?? $homeStore;
        }

        return $resolved;
    }

    /**
     * Resolve a loja activa, valida acesso e regista {@see CurrentStore}.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $next($request);
        }

        $resolved = self::resolveActiveStore($user, $request);

        $sessionId = $request->session()->get(self::SESSION_KEY);
        $sessionId = is_numeric($sessionId) ? (int) $sessionId : null;

        if ($resolved->id !== $sessionId) {
            $request->session()->put(self::SESSION_KEY, $resolved->id);
        }

        $this->currentStore->set($resolved);

        $user->loadMissing(['agent.store']);
        $accessible = $user->accessibleStores();

        View::share('activeStore', $resolved);
        View::share('selectableStores', $accessible);

        return $next($request);
    }
}
