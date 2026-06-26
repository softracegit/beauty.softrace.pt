<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\User;
use App\Notifications\AppointmentNotification;
use App\Support\ReceptionNotificationMail;
use Illuminate\Support\Facades\Log;

final class ReceptionBookingNotifier
{
    /**
     * Sininho na receção para marcações novas ou alteradas (em paralelo com a técnica).
     * Email à receção via CC nos envios à técnica.
     */
    public function notify(
        CalendarEvent $event,
        string $type,
        ?string $previousStatus = null,
        bool $fromPublicBooking = false,
    ): void {
        if ($event->event_type !== CalendarEvent::TYPE_MARCACAO || ! $event->shouldSendBookingNotifications()) {
            return;
        }

        if (! in_array($type, ['assigned', 'reassigned', 'rescheduled'], true)) {
            return;
        }

        $storeId = (int) ($event->store_id ?? 0);
        if ($storeId <= 0) {
            return;
        }

        $actorId = auth()->id();
        $technicianUserId = (int) ($event->user_id ?? 0);

        foreach (ReceptionNotificationMail::receptionUsersForStore($storeId) as $user) {
            if (
                ! $fromPublicBooking
                && $actorId !== null
                && (int) $user->id === (int) $actorId
            ) {
                continue;
            }
            if ($technicianUserId > 0 && (int) $user->id === $technicianUserId) {
                continue;
            }

            try {
                $user->notify(new AppointmentNotification(
                    (int) $event->id,
                    $type,
                    $previousStatus,
                    $fromPublicBooking,
                    forReception: true,
                ));
            } catch (\Throwable $e) {
                Log::warning('Falha ao notificar receção sobre marcação.', [
                    'calendar_event_id' => $event->id,
                    'recipient_user_id' => $user->id,
                    'type' => $type,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Sininho na receção quando uma marcação futura é cancelada (ex.: pelo cliente).
     * Email à receção via CC nos envios à técnica.
     */
    public function notifyCancellation(
        CalendarEvent $event,
        ?string $previousStatus = null,
        bool $fromPublicBooking = false,
    ): void {
        if ($event->event_type !== CalendarEvent::TYPE_MARCACAO) {
            return;
        }

        $storeId = (int) ($event->store_id ?? 0);
        if ($storeId <= 0) {
            return;
        }

        $technicianUserId = (int) ($event->user_id ?? 0);

        foreach (ReceptionNotificationMail::receptionUsersForStore($storeId) as $user) {
            if ($technicianUserId > 0 && (int) $user->id === $technicianUserId) {
                continue;
            }

            try {
                $user->notify(new AppointmentNotification(
                    (int) $event->id,
                    'status_changed',
                    $previousStatus,
                    $fromPublicBooking,
                    forReception: true,
                ));
            } catch (\Throwable $e) {
                Log::warning('Falha ao notificar receção sobre cancelamento.', [
                    'calendar_event_id' => $event->id,
                    'recipient_user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
