<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\Lead;
use App\Models\Visit;
use Illuminate\Support\Facades\Auth;

class CalendarEventService
{
    /**
     * Create a calendar event when a visit is scheduled.
     */
    public static function createFromVisit(Visit $visit): CalendarEvent
    {
        $opportunity = $visit->opportunity;
        $property = $visit->property;
        $clientName = $opportunity?->client?->name ?? 'Cliente';
        $title = sprintf('Visita: %s - %s', $property?->title ?? 'Imóvel', $clientName);

        $startAt = $visit->scheduled_at;
        $endAt = $visit->scheduled_at->copy()->addHour();

        return CalendarEvent::create([
            'title' => $title,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'description' => self::visitDescription($visit),
            'user_id' => Auth::id(),
            'event_type' => CalendarEvent::TYPE_VISITA,
            'status' => CalendarEvent::STATUS_AGENDADO,
            'eventable_type' => Visit::class,
            'eventable_id' => $visit->id,
        ]);
    }

    /**
     * Sync calendar event when a lead's scheduled_at is set or updated.
     */
    public static function syncFromLead(Lead $lead): ?CalendarEvent
    {
        if (!$lead->scheduled_at) {
            $lead->calendarEvent?->delete();
            return null;
        }

        $title = sprintf('Lead: %s', $lead->name);
        $startAt = $lead->scheduled_at;
        $endAt = $lead->scheduled_at->copy()->addHour();

        $event = $lead->calendarEvent;

        if ($event) {
            $event->update([
                'title' => $title,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'description' => $lead->notes,
            ]);
            return $event;
        }

        return CalendarEvent::create([
            'title' => $title,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'description' => $lead->notes,
            'user_id' => Auth::id(),
            'event_type' => CalendarEvent::TYPE_LEAD,
            'status' => CalendarEvent::STATUS_AGENDADO,
            'eventable_type' => Lead::class,
            'eventable_id' => $lead->id,
        ]);
    }

    private static function visitDescription(Visit $visit): string
    {
        $lines = [];
        if ($visit->opportunity) {
            $lines[] = 'Oportunidade: ' . ($visit->opportunity->reference ?? '');
        }
        if ($visit->property) {
            $lines[] = 'Imóvel: ' . ($visit->property->title ?? '') . ' (' . ($visit->property->reference ?? '') . ')';
        }
        if ($visit->notes) {
            $lines[] = $visit->notes;
        }
        return implode("\n", $lines);
    }
}
