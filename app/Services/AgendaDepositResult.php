<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Sale;

readonly class AgendaDepositResult
{
    public function __construct(
        public Booking $booking,
        public ?Sale $sale,
        public float $depositAmount,
        public int $walletAppliedCents,
        public int $stripePortionCents,
    ) {}
}
