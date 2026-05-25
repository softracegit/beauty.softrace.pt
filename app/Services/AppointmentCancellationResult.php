<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\ClientWalletTransaction;

final class AppointmentCancellationResult
{
    public function __construct(
        public readonly CalendarEvent $event,
        public readonly CancellationPolicyResult $policy,
        public readonly bool $walletCredited,
        public readonly int $walletCreditAmountCents,
        public readonly ?ClientWalletTransaction $walletTransaction = null,
        public readonly bool $alreadyCancelled = false,
    ) {}
}
