<?php

namespace Tests\Support;

use App\Models\CashRegisterSession;
use App\Models\Store;
use App\Models\User;
use App\Services\CashRegisterService;

trait OpensCashRegister
{
    protected function openCashRegisterForStore(User $user, Store $store, float $openingFloat = 0.0): CashRegisterSession
    {
        return app(CashRegisterService::class)->openSession($user, (int) $store->id, $openingFloat);
    }
}
