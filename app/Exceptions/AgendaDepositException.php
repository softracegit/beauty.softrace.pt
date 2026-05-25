<?php

namespace App\Exceptions;

use Exception;

class AgendaDepositException extends Exception
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($message);
    }
}
