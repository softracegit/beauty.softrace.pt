<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Notifications\AppointmentNotification;
use App\Support\ReceptionNotificationMail;
use Illuminate\Support\Facades\Log;

final class ReceptionBookingNotifier
{
    /**
     * Sininho + email próprio na receção para marcações novas ou alteradas.
     */
    public function notify(
        CalendarEvent $event,
        string $type,
        ?string $previousStatus = null,
        bool $fromPublicBooking = false,
        ?string $previousStartIso = null,
        ?string $previousEndIso = null,
        ?string $previousTechnicianName = null,
        array $servicesAdded = [],
        array $servicesRemoved = [],
    ): void {
        if ($event->event_type !== CalendarEvent::TYPE_MARCACAO || ! $event->shouldSendBookingNotifications()) {
            return;
        }

        if (! in_array($type, ['assigned', 'reassigned', 'rescheduled'], true)) {
            return;
        }

        $this->dispatchToReception(
            $event,
            $type,
            $previousStatus,
            $fromPublicBooking,
            $previousStartIso,
            $previousEndIso,
            $previousTechnicianName,
            $servicesAdded,
            $servicesRemoved,
        );
    }

    /**
     * Sininho + email próprio na receção quando a marcação é cancelada ou marcada como falta.
     */
    public function notifyCancellation(
        CalendarEvent $event,
        ?string $previousStatus = null,
        bool $fromPublicBooking = false,
    ): void {
        if ($event->event_type !== CalendarEvent::TYPE_MARCACAO) {
            return;
        }

        $this->dispatchToReception(
            $event,
            'status_changed',
            $previousStatus,
            $fromPublicBooking,
        );
    }

    private function dispatchToReception(
        CalendarEvent $event,
        string $type,
        ?string $previousStatus,
        bool $fromPublicBooking,
        ?string $previousStartIso = null,
        ?string $previousEndIso = null,
        ?string $previousTechnicianName = null,
        array $servicesAdded = [],
        array $servicesRemoved = [],
    ): void {
        $storeId = (int) ($event->store_id ?? 0);
        if ($storeId <= 0) {
            $event->refresh();
            $storeId = (int) ($event->store_id ?? 0);
        }
        if ($storeId <= 0) {
            return;
        }

        $technicianUserId = (int) ($event->user_id ?? 0);

        foreach (ReceptionNotificationMail::receptionUsersForStore($storeId) as $user) {
            if ($technicianUserId > 0 && (int) $user->id === $technicianUserId) {
                continue;
            }

            try {
                $user->notifyNow(new AppointmentNotification(
                    (int) $event->id,
                    $type,
                    $previousStatus,
                    $fromPublicBooking,
                    forReception: true,
                    previousStartIso: $previousStartIso,
                    previousEndIso: $previousEndIso,
                    previousTechnicianName: $previousTechnicianName,
                    servicesAdded: $servicesAdded,
                    servicesRemoved: $servicesRemoved,
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
}
