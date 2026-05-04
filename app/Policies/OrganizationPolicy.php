<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\Store;
use App\Models\User;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $user->isSuperAdmin();
    }

    public function createStore(User $user, Organization $organization): bool
    {
        return $user->isSuperAdmin();
    }

    public function updateStore(User $user, Organization $organization, Store $store): bool
    {
        if (! $user->isSuperAdmin()) {
            return false;
        }

        return (int) $store->organization_id === (int) $organization->getKey();
    }

    public function deleteStore(User $user, Organization $organization, Store $store): bool
    {
        return $this->updateStore($user, $organization, $store);
    }
}
