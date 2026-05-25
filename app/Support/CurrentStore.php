<?php

namespace App\Support;

use App\Models\Store;
use RuntimeException;

/**
 * Loja activa no backoffice (contexto por sessão, resolvida no middleware SetCurrentStore).
 */
final class CurrentStore
{
    private ?Store $store = null;

    public function set(Store $store): void
    {
        $this->store = $store;
    }

    public function get(): Store
    {
        if ($this->store === null) {
            throw new RuntimeException('Current store was not set for this request.');
        }

        return $this->store;
    }

    public function tryGet(): ?Store
    {
        return $this->store;
    }

    public function id(): int
    {
        return (int) $this->get()->getKey();
    }

    /**
     * Quando o middleware de loja não correu (ex.: booking público), devolve null.
     */
    public function tryId(): ?int
    {
        if ($this->store === null) {
            return null;
        }

        return (int) $this->store->getKey();
    }

    public function organizationId(): int
    {
        return (int) $this->get()->organization_id;
    }
}
