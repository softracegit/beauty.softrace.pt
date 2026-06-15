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
        return $user->agent !== null;
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
}
