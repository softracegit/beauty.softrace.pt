<?php

namespace App\Policies;

use Illuminate\Auth\Access\Response;
use App\Models\Agent;
use App\Models\User;

class AgentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Apenas admins e diretores podem ver a lista de agentes
        return $user->canManageAgents();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Agent $agent): bool
    {
        // Qualquer utilizador autenticado pode ver o seu próprio agente
        // Admins e diretores podem ver todos
        return $user->agent?->id === $agent->id || $user->canManageAgents();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Apenas admins e diretores podem criar agentes
        return $user->canManageAgents();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Agent $agent): bool
    {
        // Qualquer utilizador pode atualizar o seu próprio agente (dados profissionais)
        // Admins e diretores podem atualizar qualquer agente (incluindo role)
        return $user->agent?->id === $agent->id || $user->canManageAgents();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Agent $agent): bool
    {
        // Apenas admins podem eliminar agentes
        return $user->isAdmin();
    }

    /**
     * Transferir prestador para outra loja da mesma organização.
     */
    public function migrateStore(User $user, Agent $agent): bool
    {
        if (! $user->isAdmin() || $user->organization_id === null) {
            return false;
        }

        $agent->loadMissing(['store', 'user']);

        return $agent->store !== null
            && (int) $agent->store->organization_id === (int) $user->organization_id
            && $agent->user?->isPrestador() === true;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Agent $agent): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Agent $agent): bool
    {
        return false;
    }
}
