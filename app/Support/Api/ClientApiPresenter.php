<?php

namespace App\Support\Api;

use App\Models\CalendarEvent;
use App\Models\CalendarEventService;
use App\Models\User;
use App\Services\CancellationPolicyService;
use Carbon\Carbon;

/**
 * Formato JSON da conta e das marcações para a app móvel.
 */
final class ClientApiPresenter
{
    /** @return array<string, mixed> */
    public static function customer(User $user): array
    {
        $client = $user->client;

        return [
            'id' => (int) $user->id,
            'name' => (string) ($client->name ?? $user->name),
            'email' => (string) ($client->email ?? $user->email ?? ''),
            'phone' => (string) ($client->phone ?? ''),
            'birth_date' => $client?->birth_date ? Carbon::parse($client->birth_date)->format('Y-m-d') : null,
            'gender' => $client->gender ?? null,
        ];
    }

    /** @return array<string, mixed> */
    public static function booking(CalendarEvent $event, CancellationPolicyService $policies): array
    {
        $tz = $policies->businessTimezoneForStore((int) $event->store_id);
        $start = $event->start_at ? Carbon::instance($event->start_at)->timezone($tz) : null;
        $end = $event->end_at ? Carbon::instance($event->end_at)->timezone($tz) : null;

        $status = match ((string) $event->status) {
            CalendarEvent::STATUS_COMPLETO => 'completed',
            CalendarEvent::STATUS_CANCELADO, CalendarEvent::STATUS_ANULADO => 'cancelled',
            CalendarEvent::STATUS_FALTOU => 'no_show',
            default => 'scheduled',
        };

        $lines = $event->eventServiceItems->map(function (CalendarEventService $item): array {
            $extras = $item->extras->map(fn ($e) => (string) ($e->extra->name ?? ''))->filter()->values();
            $extrasTotal = (float) $item->extras->sum(fn ($e) => (float) $e->price);

            return [
                'name' => $item->lineLabel(),
                'detail' => $extras->implode(' · '),
                'price' => round((float) $item->price + $extrasTotal, 2),
            ];
        })->values();

        $canCancel = false;
        $cancelDeadline = null;
        if ($status === 'scheduled' && $start !== null && $start->gt(now($tz)) && ! $event->isMarcacaoStatusLocked()) {
            $policy = $policies->resolveForEvent($event);
            $canCancel = $policy->canCancelOnline();
            $cancelDeadline = $policy->deadlineFormatted();
        }

        return [
            'id' => (int) $event->id,
            'store_id' => (int) $event->store_id,
            'starts_at' => $start?->format('Y-m-d\TH:i'),
            'ends_at' => $end?->format('Y-m-d\TH:i'),
            'technician_name' => $event->user?->name,
            'status' => $status,
            'lines' => $lines,
            'total' => round((float) $lines->sum('price'), 2),
            'notes' => (string) ($event->description ?? ''),
            'cancelled_reason' => $status === 'cancelled' ? ($event->cancellation_reason ?? null) : null,
            'can_cancel' => $canCancel,
            'cancel_deadline' => $cancelDeadline,
        ];
    }
}
