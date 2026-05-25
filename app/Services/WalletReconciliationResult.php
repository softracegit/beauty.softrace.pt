<?php

namespace App\Services;

readonly class WalletReconciliationResult
{
    public function __construct(
        public int $clientId,
        public int $storeId,
        public int $cachedBalanceCents,
        public int $ledgerBalanceCents,
        public bool $wasFixed = false,
    ) {}

    public function isConsistent(): bool
    {
        return $this->cachedBalanceCents === $this->ledgerBalanceCents;
    }

    public function driftCents(): int
    {
        return $this->cachedBalanceCents - $this->ledgerBalanceCents;
    }
}
