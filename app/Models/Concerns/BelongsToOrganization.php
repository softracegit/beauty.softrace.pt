<?php

namespace App\Models\Concerns;

use App\Models\Store;
use App\Models\User;
use App\Support\CurrentStore;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToOrganization
{
    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where($this->getTable().'.organization_id', $organizationId);
    }

    /**
     * Compat: call sites antigos ainda passam store_id; clientes/tags são scoped à organização da loja.
     */
    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        $orgId = Store::query()->whereKey($storeId)->value('organization_id');
        if (! $orgId) {
            return $query->whereRaw('0 = 1');
        }

        return $query->forOrganization((int) $orgId);
    }

    /**
     * Implicit route binding limited to the active backoffice organization.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?? $this->getRouteKeyName();

        return static::query()
            ->where($field, $value)
            ->forOrganization($this->resolveRouteBindingOrganizationId())
            ->firstOrFail();
    }

    protected function resolveRouteBindingOrganizationId(): int
    {
        $user = auth()->user();
        if ($user instanceof User) {
            if ($user->organization_id) {
                return (int) $user->organization_id;
            }

            if ($user->isBookingClient()) {
                $user->loadMissing('client');
                if ($user->client?->organization_id) {
                    return (int) $user->client->organization_id;
                }
            }
        }

        $store = app(CurrentStore::class)->tryGet()
            ?? ($user instanceof User ? \App\Http\Middleware\SetCurrentStore::resolveActiveStore($user, request()) : null);

        if ($store instanceof Store && $store->organization_id) {
            return (int) $store->organization_id;
        }

        abort(404);
    }
}
