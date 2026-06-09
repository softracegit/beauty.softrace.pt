<?php

namespace App\Http\Middleware;

use App\Services\CashRegisterService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCashRegisterOpen
{
    public function __construct(
        private readonly CashRegisterService $cashRegisterService,
    ) {}

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->cashRegisterService->assertOpenSession((int) current_store_id());

        return $next($request);
    }
}
