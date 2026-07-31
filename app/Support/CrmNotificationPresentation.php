<?php

namespace App\Support;

/**
 * Apresentação partilhada do sininho (dropdown) e da página de notificações.
 */
final class CrmNotificationPresentation
{
    /**
     * @return array{class: string, icon: string}
     */
    public static function iconForType(?string $type): array
    {
        return match ($type) {
            'status_changed' => [
                'class' => 'danger',
                'icon' => 'bi-calendar-x',
            ],
            'rescheduled' => [
                'class' => 'warning',
                'icon' => 'bi-calendar-event',
            ],
            'reassigned' => [
                'class' => 'warning',
                'icon' => 'bi-arrow-left-right',
            ],
            default => [
                'class' => 'info',
                'icon' => 'bi-calendar-check',
            ],
        };
    }
}
