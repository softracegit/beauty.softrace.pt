<?php

namespace App\Exceptions;

use RuntimeException;

class AppointmentReactivationException extends RuntimeException
{
    public const NOT_MARCACAO = 'not_marcacao';

    public const STATUS_NOT_ELIGIBLE = 'status_not_eligible';

    public const BLOCKED = 'blocked';

    /**
     * @param  list<string>  $blockers
     */
    public function __construct(
        string $message,
        public readonly string $reasonCode,
        public readonly array $blockers = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
