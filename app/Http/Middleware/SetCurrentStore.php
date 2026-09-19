<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Models\User;
use App\Services\CashRegisterService;
use App\Support\CurrentStore;
use App\Support\StoreContextPreference;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentStore
{
    public const SESSION_KEY = 'current_store_id';

    public function __construct(
        private readonly CurrentStore $currentStore,
        private readonly CashRegisterService $cashRegisterService,
    ) {}

    /**
     * Resolve a loja activa (cookie → sessão → home), sem depender de {@see CurrentStore}.
     * Usado também no route model binding: `SubstituteBindings` corre antes deste middleware.
     */
    public static function resolveActiveStore(User $user, Request $request): Store
    {
        $user->loadMissing(['agent.store']);

        $accessible = $user->accessibleStores();
        $accessibleIds = $accessible->pluck('id')->map(fn ($id) => (int) $id)->all();

        $homeStore = $user->agent?->store;
        if ($homeStore === null) {
            $homeStore = $accessible->first();
        }
        if ($homeStore === null || $accessibleIds === []) {
            abort(503, 'A sua conta não está associada a uma loja. Contacte o administrador.');
        }

        $fromQuery = StoreContextPreference::parseStoreIdParam(
            $request->query(StoreContextPreference::QUERY_STORE)
        );
        if ($fromQuery !== null && in_array($fromQuery, $accessibleIds, true)) {
            return $accessible->firstWhere('id', $fromQuery) ?? $homeStore;
        }

        $cookieId = StoreContextPreference::readCookie($request);
        if ($cookieId !== null && in_array($cookieId, $accessibleIds, true)) {
            return $accessible->firstWhere('id', $cookieId) ?? $homeStore;
        }

        $sessionId = $request->session()->get(self::SESSION_KEY);
        $sessionId = is_numeric($sessionId) ? (int) $sessionId : null;
        if ($sessionId !== null && in_array($sessionId, $accessibleIds, true)) {
            return $accessible->firstWhere('id', $sessionId) ?? $homeStore;
        }

        return $homeStore;
    }

    /**
     * Resolve a loja activa, valida acesso e regista {@see CurrentStore}.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user() ?? auth()->user();
        if (! $user instanceof User) {
            return $next($request);
        }

        $resolved = self::resolveActiveStore($user, $request);

        $sessionId = $request->session()->get(self::SESSION_KEY);
        $sessionId = is_numeric($sessionId) ? (int) $sessionId : null;

        if ((int) $resolved->id !== $sessionId) {
            $request->session()->put(self::SESSION_KEY, $resolved->id);
        }

        $queryStore = $request->query(StoreContextPreference::QUERY_STORE);
        $wantsAll = StoreContextPreference::isAllScopeParam($queryStore)
            || (($queryStore === null || $queryStore === '') && StoreContextPreference::cookieIsAll($request));

        if ($wantsAll) {
            StoreContextPreference::persistAll($request);
        } else {
            $cookieId = StoreContextPreference::readCookie($request);
            if ($cookieId !== (int) $resolved->id) {
                StoreContextPreference::persist($request, (int) $resolved->id);
            }
        }

        $this->currentStore->set($resolved);

        $user->loadMissing(['agent.store']);
        $accessible = $user->accessibleStores();

        View::share('activeStore', $resolved);
        View::share('selectableStores', $accessible);
        View::share('canChooseStoreContext', StoreContextPreference::canChooseStore($user));

        $cashRegisterSession = null;
        $cashRegisterCanManage = false;
        if ($this->cashRegisterService->userCanManageCashRegister($user)) {
            $cashRegisterCanManage = true;
            $cashRegisterSession = $this->cashRegisterService->getOpenSession((int) $resolved->id);
        }
        View::share('cashRegisterSession', $cashRegisterSession);
        View::share('cashRegisterCanManage', $cashRegisterCanManage);

        return $next($request);
    }
}
