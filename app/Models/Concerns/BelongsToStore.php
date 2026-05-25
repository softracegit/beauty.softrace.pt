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

        $fromRoute = $this->resolveStoreIdFromRouteParameter();
        if ($fromRoute !== null) {
            return $fromRoute;
        }

        $user = auth()->user();
        if ($user instanceof User) {
            if ($user->isBookingClient()) {
                $user->loadMissing('client');
                if ($user->client !== null) {
                    return (int) $user->client->store_id;
                }
            }

            return (int) SetCurrentStore::resolveActiveStore($user, request())->getKey();
        }

        return Store::defaultPublicBookingStoreId();
    }

    /**
     * Loja do segmento `{store}` nas rotas `/booking/{store}/…` (binding corre antes de BookingContext).
     */
    protected function resolveStoreIdFromRouteParameter(): ?int
    {
        $route = request()->route();
        if ($route === null) {
            return null;
        }

        $name = (string) ($route->getName() ?? '');
        if ($name !== '' && ! str_starts_with($name, 'booking.')) {
            return null;
        }

        $store = $route->parameter('store');
        if ($store instanceof Store) {
            return (int) $store->id;
        }

        $slug = is_string($store) || is_numeric($store) ? trim((string) $store) : '';
        if ($slug === '') {
            return null;
        }

        $id = Store::query()->where('slug', $slug)->value('id');

        return $id !== null ? (int) $id : null;
    }
}
