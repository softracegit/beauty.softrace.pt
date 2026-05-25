<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientWalletBalanceException extends RuntimeException
{
    public function __construct(
        public readonly int $clientId,
        public readonly int $requestedCents,
        public readonly int $availableCents,
    ) {
        parent::__construct(sprintf(
            'Insufficient wallet balance for client %d (requested %d cents, available %d cents).',
            $clientId,
            $requestedCents,
            $availableCents,
        ));
    }
}
