<?php

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
        return current_store()->id();
    }
}
