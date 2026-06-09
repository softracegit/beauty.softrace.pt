<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashRegisterNotOpenException extends Exception
{
    public function __construct(
        string $message = 'Abra o dia na caixa antes de cobrar.',
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['error' => $this->getMessage()], 422);
    }
}
