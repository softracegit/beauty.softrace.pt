<?php

namespace App\Exceptions;

use RuntimeException;

class AppointmentCancellationException extends RuntimeException
{
    public const ALREADY_CANCELLED = 'already_cancelled';

    public const NOT_MARCACAO = 'not_marcacao';

    public const STATUS_LOCKED = 'status_locked';

    public const OUTSIDE_NOTICE_PERIOD = 'outside_notice_period';

    public const PAST_ONLINE_CUTOFF = 'past_online_cutoff';

    public function __construct(
        string $message,
        public readonly string $reasonCode,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
