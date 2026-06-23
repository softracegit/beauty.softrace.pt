<?php

use App\Http\Middleware\SetCurrentStore;
use App\Models\User;
use App\Support\CurrentStore;

if (! function_exists('static_asset_version')) {
    /**
     * Versão para query string (?v=) — mtime do ficheiro em public/.
     * Actualiza automaticamente em cada deploy que altere o ficheiro.
     */
    function static_asset_version(string $publicPath): string
    {
        $publicPath = ltrim(str_replace('\\', '/', $publicPath), '/');
        $full = public_path($publicPath);
        $mtime = is_file($full) ? (int) filemtime($full) : time();
        $deploy = trim((string) config('app.asset_version', ''));

        return $deploy !== '' ? ($deploy.'-'.$mtime) : (string) $mtime;
    }
}

if (! function_exists('static_asset')) {
    function static_asset(string $publicPath): string
    {
        return asset($publicPath).'?v='.static_asset_version($publicPath);
    }
}

if (! function_exists('current_store')) {
    /**
     * Instância da loja activa no backoffice (definida por {@see \App\Http\Middleware\SetCurrentStore}).
     */
    function current_store(): CurrentStore
    {
        return app(CurrentStore::class);
    }
}

if (! function_exists('current_store_id')) {
    function current_store_id(): int
    {
        $current = app(CurrentStore::class);
        $id = $current->tryId();
        if ($id !== null) {
            return $id;
        }

        $user = auth()->user();
        if ($user instanceof User) {
            $store = SetCurrentStore::resolveActiveStore($user, request());
            $current->set($store);

            return (int) $store->getKey();
        }

        return $current->id();
    }
}
