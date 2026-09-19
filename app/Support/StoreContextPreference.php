<?php

namespace App\Support;

use App\Http\Middleware\SetCurrentStore;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Preferência de loja operacional (cookie + sessão).
 * Usada na agenda / dashboards / relatórios; não amarra catálogo nem definições.
 */
final class StoreContextPreference
{
    public const COOKIE_NAME = 'crm_store_context';

    /** 1 ano */
    public const COOKIE_MINUTES = 525600;

    public const QUERY_STORE = 'loja';

    public const SCOPE_ALL = 'todas';

    /**
     * Resolve uma loja concreta acessível (nunca "todas").
     * Ordem: query ?loja=ID → cookie → sessão → home.
     */
    public static function resolveStoreId(User $user, Request $request): int
    {
        $accessible = $user->accessibleStores();
        $ids = $accessible->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($ids === []) {
            abort(503, 'A sua conta não está associada a uma loja. Contacte o administrador.');
        }

        $fromQuery = self::parseStoreIdParam($request->query(self::QUERY_STORE));
        if ($fromQuery !== null && in_array($fromQuery, $ids, true)) {
            return $fromQuery;
        }

        $fromCookie = self::readCookie($request);
        if ($fromCookie !== null && in_array($fromCookie, $ids, true)) {
            return $fromCookie;
        }

        $sessionId = $request->session()->get(SetCurrentStore::SESSION_KEY);
        $sessionId = is_numeric($sessionId) ? (int) $sessionId : null;
        if ($sessionId !== null && in_array($sessionId, $ids, true)) {
            return $sessionId;
        }

        $home = $user->agent?->store_id;
        if ($home !== null && in_array((int) $home, $ids, true)) {
            return (int) $home;
        }

        return $ids[0];
    }

    /**
     * Para páginas com opção "Todas": devolve store_id ou null (= todas).
     * Query ?loja=todas → null; ?loja=ID → ID; cookie "todas" → null; senão loja concreta.
     *
     * @return array{store_id: ?int, scope: 'loja'|'todas', store_ids: list<int>}
     */
    public static function resolveFilter(User $user, Request $request, bool $allowAll = true): array
    {
        $accessible = $user->accessibleStores();
        $ids = $accessible->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        if ($ids === []) {
            abort(503, 'A sua conta não está associada a uma loja. Contacte o administrador.');
        }

        $raw = $request->query(self::QUERY_STORE);
        if ($allowAll && self::isAllScopeParam($raw)) {
            return [
                'store_id' => null,
                'scope' => self::SCOPE_ALL,
                'store_ids' => $ids,
            ];
        }

        if ($allowAll && ($raw === null || $raw === '') && self::cookieIsAll($request)) {
            return [
                'store_id' => null,
                'scope' => self::SCOPE_ALL,
                'store_ids' => $ids,
            ];
        }

        $storeId = self::resolveStoreId($user, $request);

        return [
            'store_id' => $storeId,
            'scope' => 'loja',
            'store_ids' => [$storeId],
        ];
    }

    /**
     * Equipa: por defeito todas; ?loja=ID filtra.
     *
     * @return array{store_id: ?int, scope: 'loja'|'todas', store_ids: list<int>}
     */
    public static function resolveEquipaFilter(User $user, Request $request): array
    {
        $accessible = $user->accessibleStores();
        $ids = $accessible->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        if ($ids === []) {
            abort(503, 'A sua conta não está associada a uma loja. Contacte o administrador.');
        }

        $raw = $request->query(self::QUERY_STORE);
        if ($raw === null || $raw === '') {
            return [
                'store_id' => null,
                'scope' => self::SCOPE_ALL,
                'store_ids' => $ids,
            ];
        }

        return self::resolveFilter($user, $request, true);
    }

    public static function persist(Request $request, int $storeId): void
    {
        $request->session()->put(SetCurrentStore::SESSION_KEY, $storeId);
        self::queueCookie((string) $storeId);
    }

    /** Persiste preferência "todas as lojas" no cookie (mantém a sessão na loja activa para mutações). */
    public static function persistAll(Request $request): void
    {
        self::queueCookie(self::SCOPE_ALL);
    }

    public static function readCookie(Request $request): ?int
    {
        return self::parseStoreIdParam($request->cookie(self::COOKIE_NAME));
    }

    public static function cookieIsAll(Request $request): bool
    {
        return self::isAllScopeParam($request->cookie(self::COOKIE_NAME));
    }

    public static function isAllScopeParam(mixed $raw): bool
    {
        return is_string($raw) && strtolower(trim($raw)) === self::SCOPE_ALL;
    }

    public static function parseStoreIdParam(mixed $raw): ?int
    {
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return null;
        }

        $id = (int) $raw;

        return $id > 0 ? $id : null;
    }

    private static function queueCookie(string $value): void
    {
        cookie()->queue(cookie(
            self::COOKIE_NAME,
            $value,
            self::COOKIE_MINUTES,
            '/',
            null,
            null,
            false,
            false,
            'lax'
        ));
    }

    /**
     * @return Collection<int, Store>
     */
    public static function selectableStores(User $user): Collection
    {
        return $user->accessibleStores();
    }

    public static function canChooseStore(User $user): bool
    {
        return $user->canSwitchStore();
    }
}
