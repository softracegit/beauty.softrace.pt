<?php

namespace App\Http\Concerns;

use App\Models\User;
use Illuminate\Http\JsonResponse;

trait DeniesPrestadorPayments
{
    protected function denyPrestadorPaymentsJson(): ?JsonResponse
    {
        $user = auth()->user();
        if ($user instanceof User && $user->isPrestador()) {
            return response()->json(['error' => 'Sem permissão para processar pagamentos.'], 403);
        }

        return null;
    }
}
