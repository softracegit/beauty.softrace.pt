<?php

namespace App\Services;

use App\Models\CalendarEvent;

class AppointmentReactivationResult
{
    public function __construct(
        public readonly CalendarEvent $event,
        public readonly string $previousStatus,
        public readonly bool $clientNotified,
    ) {}
}
