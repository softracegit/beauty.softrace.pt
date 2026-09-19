<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;

class StorePolicy
{
    /**
     * Utilizadores com agente podem listar lojas acessíveis via {@see User::accessibleStores()}.
     */
    public function viewAny(User $user): bool
    {
        return $user->agent !== null || $user->isAdmin();
    }

    public function view(User $user, Store $store): bool
    {
        return $user->accessibleStores()->contains('id', $store->id);
    }

    /**
     * Mudar a loja activa na sessão do backoffice.
     */
    public function switchTo(User $user, Store $store): bool
    {
        return $user->canSwitchStore() && $this->view($user, $store);
    }

    /**
     * Criar loja na organização do admin da empresa.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() && $user->organization_id !== null;
    }

    public function update(User $user, Store $store): bool
    {
        return $this->manageStore(user: $user, store: $store);
    }

    public function delete(User $user, Store $store): bool
    {
        return $this->manageStore(user: $user, store: $store);
    }

    private function manageStore(User $user, Store $store): bool
    {
        if (! $user->isAdmin() || $user->organization_id === null) {
            return false;
        }

        return (int) $store->organization_id === (int) $user->organization_id;
    }
}
