<?php

use App\Http\Middleware\SetCurrentStore;
use App\Models\User;
use App\Support\CurrentStore;

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
