<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;

class ActivityLogContext
{
    public static function clientName(Client $client): string
    {
        $name = trim((string) $client->name);

        return $name !== '' ? $name : '#'.$client->id;
    }

    public static function marcacaoLine(CalendarEvent $event): ?string
    {
        $event->loadMissing(['client', 'eventServices', 'service']);

        $parts = [];

        $clientName = trim((string) ($event->client?->name ?? ''));
        if ($clientName !== '') {
            $parts[] = $clientName;
        }

        if ($event->start_at) {
            $parts[] = DateTimeDisplay::marcacao($event->start_at, (int) $event->store_id, 'd/m/Y H:i');
        }

        $serviceNames = $event->relationLoaded('eventServices') && $event->eventServices->isNotEmpty()
            ? $event->eventServices->pluck('name')->map(fn ($n) => trim((string) $n))->filter()->values()->all()
            : [];

        if ($serviceNames === [] && $event->service) {
            $fallback = trim((string) $event->service->name);
            if ($fallback !== '') {
                $serviceNames = [$fallback];
            }
        }

        if ($serviceNames !== []) {
            $parts[] = implode(', ', $serviceNames);
        }

        return $parts !== [] ? implode(' · ', $parts) : null;
    }

    /**
     * Linha de contexto para exibição: usa valor gravado ou reconstrói a partir do subject (logs antigos).
     */
    public static function resolveForActivity(Activity $activity): ?string
    {
        $stored = self::contextFromProperties($activity->properties);
        if ($stored !== null) {
            return $stored;
        }

        return self::resolveFromSubject($activity->subject);
    }

    public static function attachMarcacao(ActivityContract $activity, CalendarEvent $event): void
    {
        if (($event->event_type ?? '') !== CalendarEvent::TYPE_MARCACAO) {
            return;
        }

        $line = self::marcacaoLine($event);
        if ($line === null) {
            return;
        }

        self::attachLine($activity, $line);
    }

    public static function attachClient(ActivityContract $activity, Client $client): void
    {
        self::attachSubjectLabel($activity, self::clientName($client));
    }

    public static function attachSubjectLabel(ActivityContract $activity, string $label): void
    {
        $label = trim($label);
        if ($label === '') {
            return;
        }

        self::attachLine($activity, $label);
    }

    public static function contextFromProperties(mixed $properties): ?string
    {
        if ($properties === null) {
            return null;
        }

        if (is_object($properties) && method_exists($properties, 'get')) {
            $line = $properties->get('contexto');

            return is_string($line) && $line !== '' ? $line : null;
        }

        if (is_array($properties)) {
            $line = $properties['contexto'] ?? null;

            return is_string($line) && $line !== '' ? $line : null;
        }

        return null;
    }

    private static function resolveFromSubject(?Model $subject): ?string
    {
        if ($subject instanceof CalendarEvent && ($subject->event_type ?? '') === CalendarEvent::TYPE_MARCACAO) {
            return self::marcacaoLine($subject);
        }

        if ($subject instanceof Client) {
            return self::clientName($subject);
        }

        if ($subject instanceof Agent) {
            $name = trim((string) $subject->name);

            return $name !== '' ? $name : '#'.$subject->id;
        }

        if ($subject instanceof User) {
            $name = trim((string) $subject->name);

            return $name !== '' ? $name : '#'.$subject->id;
        }

        return null;
    }

    private static function attachLine(ActivityContract $activity, string $line): void
    {
        $props = $activity->properties;

        if ($props instanceof \Illuminate\Support\Collection) {
            $array = $props->toArray();
        } elseif (is_object($props) && method_exists($props, 'toArray')) {
            $array = $props->toArray();
        } elseif (is_array($props)) {
            $array = $props;
        } else {
            $array = [];
        }

        $array['contexto'] = $line;
        $activity->properties = $array;
    }
}
