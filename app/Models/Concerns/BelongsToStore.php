<?php

namespace App\Models\Concerns;

use App\Http\Middleware\SetCurrentStore;
use App\Models\Store;
use App\Models\User;
use App\Support\CurrentStore;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToStore
{
    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where($this->getTable().'.store_id', $storeId);
    }

    /**
     * Implicit route binding limited to the active backoffice store.
     *
     * `SubstituteBindings` runs before {@see SetCurrentStore}, so {@see CurrentStore}
     * may be empty; replica a resolução da loja com sessão + utilizador autenticado.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?? $this->getRouteKeyName();

        return static::query()
            ->where($field, $value)
            ->forStore($this->resolveRouteBindingStoreId())
            ->firstOrFail();
    }

    protected function resolveRouteBindingStoreId(): int
    {
        $bound = app(CurrentStore::class)->tryId();
        if ($bound !== null) {
            return $bound;
        }

        $user = auth()->user();
        if ($user instanceof User) {
            return (int) SetCurrentStore::resolveActiveStore($user, request())->getKey();
        }

        return Store::defaultPublicBookingStoreId();
    }
}
