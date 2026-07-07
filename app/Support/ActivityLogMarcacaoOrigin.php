<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\CalendarEvent;
use App\Models\User;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;

class ActivityLogMarcacaoOrigin
{
    public const ONLINE = 'online';

    public const AGENDA = 'agenda';

    public const IMPORT = 'import';

    public const SISTEMA = 'sistema';

    public static function label(?string $origem): ?string
    {
        return match ($origem) {
            self::ONLINE => 'marcação online',
            self::AGENDA => 'agenda',
            self::IMPORT => 'importação',
            self::SISTEMA => 'sistema',
            default => null,
        };
    }

    public static function resolveForCreation(CalendarEvent $event): string
    {
        if (($event->event_type ?? '') === CalendarEvent::TYPE_MARCACAO) {
            $stored = CalendarEvent::normalizeMarcacaoSource($event->marcacao_source);
            if ($stored !== null) {
                return $stored;
            }
        }

        $user = auth()->user();
        if ($user instanceof User && $user->isBookingClient()) {
            return self::ONLINE;
        }

        $routeName = (string) (request()->route()?->getName() ?? '');
        if ($routeName !== '' && str_starts_with($routeName, 'booking.')) {
            return self::ONLINE;
        }

        if (str_contains(trim((string) $event->description), '[Importado Zappy]')) {
            return self::IMPORT;
        }

        if ($user instanceof User && ! $user->isBookingClient()) {
            return self::AGENDA;
        }

        return self::SISTEMA;
    }

    public static function inferFromActivity(Activity $activity): ?string
    {
        $stored = self::fromProperties($activity->properties);
        if ($stored !== null) {
            return $stored;
        }

        $causer = $activity->causer;
        if ($causer instanceof User && $causer->isBookingClient()) {
            return self::ONLINE;
        }

        $subject = $activity->subject;
        if ($subject instanceof CalendarEvent) {
            $stored = CalendarEvent::normalizeMarcacaoSource($subject->marcacao_source);
            if ($stored !== null) {
                return $stored;
            }

            $subject->loadMissing('onlineBooking');
            if ($subject->onlineBooking) {
                return self::ONLINE;
            }

            if (str_contains(trim((string) $subject->description), '[Importado Zappy]')) {
                return self::IMPORT;
            }
        }

        if ($causer instanceof User && ! $causer->isBookingClient()) {
            return self::AGENDA;
        }

        return null;
    }

    public static function fromProperties(mixed $properties): ?string
    {
        if ($properties === null) {
            return null;
        }

        if (is_object($properties) && method_exists($properties, 'get')) {
            $origem = $properties->get('origem');
        } elseif (is_array($properties)) {
            $origem = $properties['origem'] ?? null;
        } else {
            $origem = null;
        }

        return is_string($origem) && self::label($origem) !== null ? $origem : null;
    }

    public static function attach(ActivityContract $activity, string $origem): void
    {
        if (self::label($origem) === null) {
            return;
        }

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

        $array['origem'] = $origem;
        $activity->properties = $array;
    }
}
