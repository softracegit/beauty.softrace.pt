<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\Extra;
use App\Models\Service;
use App\Models\User;
use App\Support\ActivityLogContext;
use Illuminate\Support\Collection;

class MarcacaoServicesActivityLogger
{
    /**
     * @param  list<array<string, mixed>>  $snapshot
     */
    public function logAssociated(CalendarEvent $event, array $snapshot, ?User $causer = null): void
    {
        $labels = $this->labelsFromSnapshot($snapshot, (int) $event->store_id);
        if ($labels === []) {
            return;
        }

        $this->write($event, 'servicos_alterados', 'Serviços associados à nova marcação', [
            'alteracoes' => $labels,
        ], $causer);
    }

    /**
     * @param  list<array<string, mixed>>  $before
     * @param  list<array<string, mixed>>  $after
     */
    public function logChanged(
        CalendarEvent $event,
        array $before,
        array $after,
        ?User $causer = null,
    ): void {
        $storeId = (int) $event->store_id;
        $lines = $this->changeLines($before, $after, $storeId);
        if ($lines === []) {
            return;
        }

        $this->write($event, 'servicos_alterados', 'Serviços ou extras da marcação alterados', [
            'alteracoes' => $lines,
        ], $causer);
    }

    /**
     * @param  list<array<string, mixed>>  $before
     * @param  list<array<string, mixed>>  $after
     * @return array{added: list<string>, removed: list<string>}
     */
    public function addedAndRemovedLabels(array $before, array $after, int $storeId): array
    {
        $catalog = $this->loadCatalog($before, $after, $storeId);
        $beforeLabels = [];
        foreach ($before as $row) {
            $label = $this->labelFromSnapshotRow($row, $catalog);
            if ($label !== null && $label !== '') {
                $beforeLabels[] = $label;
            }
        }
        $afterLabels = [];
        foreach ($after as $row) {
            $label = $this->labelFromSnapshotRow($row, $catalog);
            if ($label !== null && $label !== '') {
                $afterLabels[] = $label;
            }
        }

        $beforeCount = array_count_values($beforeLabels);
        $afterCount = array_count_values($afterLabels);
        $added = [];
        $removed = [];

        foreach (array_unique(array_merge(array_keys($beforeCount), array_keys($afterCount))) as $label) {
            $b = $beforeCount[$label] ?? 0;
            $a = $afterCount[$label] ?? 0;
            for ($i = 0; $i < ($a - $b); $i++) {
                $added[] = $label;
            }
            for ($i = 0; $i < ($b - $a); $i++) {
                $removed[] = $label;
            }
        }

        return ['added' => $added, 'removed' => $removed];
    }

    /**
     * @param  list<array<string, mixed>>  $before
     * @param  list<array<string, mixed>>  $after
     * @return list<string>
     */
    public function changeLines(array $before, array $after, int $storeId): array
    {
        $catalog = $this->loadCatalog($before, $after, $storeId);
        $lines = [];
        $max = max(count($before), count($after));

        for ($i = 0; $i < $max; $i++) {
            $beforeLabel = isset($before[$i]) ? $this->labelFromSnapshotRow($before[$i], $catalog) : null;
            $afterLabel = isset($after[$i]) ? $this->labelFromSnapshotRow($after[$i], $catalog) : null;

            if ($beforeLabel === $afterLabel) {
                continue;
            }

            if ($beforeLabel !== null && $afterLabel !== null) {
                $lines[] = $beforeLabel.' → '.$afterLabel;
            } elseif ($beforeLabel !== null) {
                $lines[] = $beforeLabel.' → (removido)';
            } elseif ($afterLabel !== null) {
                $lines[] = '(adicionado) → '.$afterLabel;
            }
        }

        return $lines;
    }

    /**
     * @param  list<array<string, mixed>>  $snapshot
     * @return list<string>
     */
    private function labelsFromSnapshot(array $snapshot, int $storeId): array
    {
        $catalog = $this->loadCatalog($snapshot, $snapshot, $storeId);
        $labels = [];

        foreach ($snapshot as $row) {
            $label = $this->labelFromSnapshotRow($row, $catalog);
            if ($label !== null && $label !== '') {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    /**
     * @param  list<array<string, mixed>>  ...$snapshots
     * @return array{services: Collection<int, Service>, extras: Collection<int, Extra>}
     */
    private function loadCatalog(array $first, array $second, int $storeId): array
    {
        $serviceIds = [];
        $extraIds = [];

        foreach ([$first, $second] as $snapshot) {
            foreach ($snapshot as $row) {
                $serviceId = (int) ($row['service_id'] ?? 0);
                if ($serviceId > 0) {
                    $serviceIds[$serviceId] = true;
                }
                foreach ($row['extras'] ?? [] as $extraRow) {
                    $extraId = (int) ($extraRow['extra_id'] ?? 0);
                    if ($extraId > 0) {
                        $extraIds[$extraId] = true;
                    }
                }
            }
        }

        $services = $serviceIds === []
            ? collect()
            : Service::query()
                ->forStore($storeId)
                ->whereIn('id', array_keys($serviceIds))
                ->with(['options' => fn ($q) => $q->orderBy('sort_order')])
                ->get()
                ->keyBy('id');

        $extras = $extraIds === []
            ? collect()
            : Extra::query()
                ->whereIn('id', array_keys($extraIds))
                ->get()
                ->keyBy('id');

        return [
            'services' => $services,
            'extras' => $extras,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{services: Collection<int, Service>, extras: Collection<int, Extra>}  $catalog
     */
    private function labelFromSnapshotRow(array $row, array $catalog): ?string
    {
        $serviceId = (int) ($row['service_id'] ?? 0);
        if ($serviceId <= 0) {
            return null;
        }

        /** @var Service|null $service */
        $service = $catalog['services']->get($serviceId);
        $optionId = isset($row['service_option_id']) && $row['service_option_id'] !== null
            ? (int) $row['service_option_id']
            : null;

        $base = '';
        if ($optionId && $service) {
            $option = $service->options->firstWhere('id', $optionId);
            $base = trim((string) ($option?->name ?? ''));
        }
        if ($base === '') {
            $base = trim((string) ($service?->name ?? ''));
        }
        if ($base === '') {
            $base = 'Serviço #'.$serviceId;
        }

        $extraLabels = [];
        foreach ($row['extras'] ?? [] as $extraRow) {
            $extraId = (int) ($extraRow['extra_id'] ?? 0);
            if ($extraId <= 0) {
                continue;
            }
            /** @var Extra|null $extra */
            $extra = $catalog['extras']->get($extraId);
            $extraName = trim((string) ($extra?->name ?? ''));
            if ($extraName !== '') {
                $extraLabels[] = $extraName;
            }
        }

        if ($extraLabels !== []) {
            $base .= ' + '.implode(', ', $extraLabels);
        }

        return $base;
    }

    private function write(CalendarEvent $event, string $eventName, string $description, array $properties, ?User $causer): void
    {
        $line = ActivityLogContext::marcacaoLine($event);
        if ($line !== null) {
            $properties['contexto'] = $line;
        }

        $logger = activity()
            ->performedOn($event)
            ->event($eventName)
            ->withProperties($properties);

        $causer ??= auth()->user();
        if ($causer instanceof User) {
            $logger->causedBy($causer);
        }

        $logger->log($description);
    }
}
