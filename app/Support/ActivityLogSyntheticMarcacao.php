<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\CalendarEvent;
use App\Models\ZappyImportRef;
use Illuminate\Support\Collection;

class ActivityLogSyntheticMarcacao
{
    /**
     * Marcações importadas do Zappy (sem evento `created` no activity log) recebem entrada sintética.
     *
     * @param  Collection<int, Activity>  $activities
     * @return Collection<int, Activity>
     */
    public static function injectMissingZappyCreation(Collection $activities, CalendarEvent $event): Collection
    {
        $synthetic = self::missingZappyCreationEntry($event, $activities);
        if ($synthetic === null) {
            return $activities;
        }

        return $activities
            ->push($synthetic)
            ->sortByDesc(fn (Activity $activity) => sprintf(
                '%s-%010d',
                $activity->created_at?->format('Y-m-d H:i:s.u') ?? '',
                (int) $activity->id,
            ))
            ->values();
    }

    public static function missingZappyCreationEntry(CalendarEvent $event, Collection $activities): ?Activity
    {
        if (($event->event_type ?? '') !== CalendarEvent::TYPE_MARCACAO) {
            return null;
        }

        if (self::hasCreationLog($activities)) {
            return null;
        }

        if (! self::isZappyImport($event)) {
            return null;
        }

        return self::buildZappyCreationEntry($event);
    }

    /**
     * @param  Collection<int, Activity>  $activities
     */
    public static function hasCreationLog(Collection $activities): bool
    {
        return $activities->contains(function (Activity $activity): bool {
            if (($activity->event ?? '') !== 'created') {
                return false;
            }

            $description = trim((string) ($activity->description ?? ''));

            return preg_match('/^Marcação criada(\s*\(|$)/u', $description) === 1;
        });
    }

    public static function isZappyImport(CalendarEvent $event): bool
    {
        if (str_contains(trim((string) $event->description), '[Importado Zappy]')) {
            return true;
        }

        if ((int) $event->getKey() <= 0) {
            return false;
        }

        return ZappyImportRef::query()
            ->where('store_id', (int) $event->store_id)
            ->where('local_id', (int) $event->getKey())
            ->whereIn('entity_type', [
                ZappyImportRef::TYPE_APPOINTMENT,
                ZappyImportRef::TYPE_APPOINTMENT_ZAPPY,
            ])
            ->exists();
    }

    public static function zappyCauserLabel(): string
    {
        $crmName = trim((string) config('app.name'));

        if ($crmName === '') {
            return 'Importação do Zappy';
        }

        return 'Importação do Zappy para '.$crmName;
    }

    private static function buildZappyCreationEntry(CalendarEvent $event): Activity
    {
        $contextLine = ActivityLogContext::marcacaoLine($event);

        $properties = [
            'synthetic' => true,
            'origem' => ActivityLogMarcacaoOrigin::IMPORT,
            'synthetic_causer_label' => self::zappyCauserLabel(),
        ];

        if ($contextLine !== null) {
            $properties['contexto'] = $contextLine;
        }

        $activity = new Activity([
            'event' => 'created',
            'description' => 'Marcação criada (importação)',
            'subject_type' => $event->getMorphClass(),
            'subject_id' => (int) $event->getKey(),
            'store_id' => $event->store_id,
            'created_at' => $event->created_at,
        ]);
        $activity->id = 0;
        $activity->properties = $properties;
        $activity->setRelation('subject', $event);
        $activity->setRelation('causer', null);

        return $activity;
    }
}
