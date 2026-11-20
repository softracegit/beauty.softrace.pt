<?php

namespace App\Services;

use App\Models\Client;
use App\Models\SmsMessage;
use App\Support\StoreBusinessTime;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SmsReportService
{
    /**
     * @param  list<int>  $storeIds
     * @return array{today: int, week: int, month: int}
     */
    public function summaryCounts(array $storeIds): array
    {
        $storeIds = $this->normalizeStoreIds($storeIds);
        $anchorStoreId = $storeIds[0];
        $now = StoreBusinessTime::nowForStore($anchorStoreId);
        $todayStart = $now->copy()->startOfDay()->utc();
        $todayEnd = $now->copy()->endOfDay()->utc();
        $weekStart = $now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay()->utc();
        $monthStart = $now->copy()->startOfMonth()->startOfDay()->utc();

        $base = SmsMessage::query()->whereIn('store_id', $storeIds);

        return [
            'today' => (clone $base)->whereBetween('sent_at', [$todayStart, $todayEnd])->count(),
            'week' => (clone $base)->where('sent_at', '>=', $weekStart)->where('sent_at', '<=', $todayEnd)->count(),
            'month' => (clone $base)->where('sent_at', '>=', $monthStart)->where('sent_at', '<=', $todayEnd)->count(),
        ];
    }

    /**
     * @param  list<int>  $storeIds
     * @param  array{
     *   desde?: ?string,
     *   ate?: ?string,
     *   cliente?: mixed,
     *   tipo?: ?string,
     *   telemovel?: ?string
     * }  $filters
     */
    public function reportQuery(array $storeIds, array $filters = []): Builder
    {
        $storeIds = $this->normalizeStoreIds($storeIds);
        $anchorStoreId = $storeIds[0];
        $tz = StoreBusinessTime::timezoneForStore($anchorStoreId);

        $desde = $filters['desde'] ?? null;
        $ate = $filters['ate'] ?? null;
        if (! is_string($desde) || $desde === '') {
            $desde = StoreBusinessTime::nowForStore($anchorStoreId)->copy()->startOfMonth()->toDateString();
        }
        if (! is_string($ate) || $ate === '') {
            $ate = StoreBusinessTime::nowForStore($anchorStoreId)->copy()->endOfMonth()->toDateString();
        }

        $start = Carbon::createFromFormat('Y-m-d', $desde, $tz)->startOfDay()->utc();
        $end = Carbon::createFromFormat('Y-m-d', $ate, $tz)->endOfDay()->utc();

        $query = SmsMessage::query()
            ->whereIn('store_id', $storeIds)
            ->whereBetween('sent_at', [$start, $end])
            ->with(['client:id,name']);

        $cliente = $filters['cliente'] ?? null;
        if ($cliente !== null && $cliente !== '') {
            $query->where('client_id', (int) $cliente);
        }

        $tipo = $filters['tipo'] ?? null;
        if (is_string($tipo) && $tipo !== '' && array_key_exists($tipo, SmsMessage::typeLabels())) {
            $query->where('type', $tipo);
        }

        $telemovel = $filters['telemovel'] ?? null;
        if (is_string($telemovel) && trim($telemovel) !== '') {
            $raw = trim($telemovel);
            $digits = preg_replace('/\D+/', '', $raw) ?: '';
            $query->where(function (Builder $q) use ($raw, $digits) {
                $q->where('to_phone', 'like', '%'.$raw.'%');
                if ($digits !== '' && $digits !== $raw) {
                    $q->orWhere('to_phone', 'like', '%'.$digits.'%');
                }
            });
        }

        return $query;
    }

    /**
     * @param  list<int>  $storeIds
     * @return Collection<int, object{id: int, name: string}>
     */
    public function clientesOpts(array $storeIds): Collection
    {
        $storeIds = $this->normalizeStoreIds($storeIds);
        $clientIds = SmsMessage::query()
            ->whereIn('store_id', $storeIds)
            ->whereNotNull('client_id')
            ->distinct()
            ->pluck('client_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        if ($clientIds === []) {
            return collect();
        }

        return Client::query()
            ->whereIn('id', $clientIds)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Estimativa de custo Twilio (USD→EUR). 1 registo ≈ 1 segmento.
     *
     * @return array{
     *   count: int,
     *   unit_usd: float,
     *   unit_eur: float,
     *   total_usd: float,
     *   total_eur: float,
     *   usd_to_eur: float
     * }
     */
    public function estimateCost(int $count): array
    {
        $count = max(0, $count);
        $unitUsd = max(0.0, (float) config('services.twilio.sms_cost_usd', 0.05010));
        $usdToEur = max(0.0, (float) config('services.twilio.sms_usd_to_eur', 0.92));
        $unitEur = round($unitUsd * $usdToEur, 5);
        $totalUsd = round($count * $unitUsd, 4);
        $totalEur = round($count * $unitEur, 2);

        return [
            'count' => $count,
            'unit_usd' => $unitUsd,
            'unit_eur' => $unitEur,
            'total_usd' => $totalUsd,
            'total_eur' => $totalEur,
            'usd_to_eur' => $usdToEur,
        ];
    }

    /**
     * Envios e custo aproximado por dia civil (fuso da loja) no período filtrado.
     *
     * @param  list<int>  $storeIds
     * @param  array{
     *   desde?: ?string,
     *   ate?: ?string,
     *   cliente?: mixed,
     *   tipo?: ?string,
     *   telemovel?: ?string
     * }  $filters
     * @return array{
     *   labels: list<string>,
     *   dates: list<string>,
     *   counts: list<int>,
     *   costs_eur: list<float>,
     *   total_count: int,
     *   total_cost_eur: float,
     *   bucket_days: int
     * }
     */
    public function dailyBreakdown(array $storeIds, array $filters = []): array
    {
        $storeIds = $this->normalizeStoreIds($storeIds);
        $anchorStoreId = $storeIds[0];
        $tz = StoreBusinessTime::timezoneForStore($anchorStoreId);

        $desde = $filters['desde'] ?? null;
        $ate = $filters['ate'] ?? null;
        if (! is_string($desde) || $desde === '') {
            $desde = StoreBusinessTime::nowForStore($anchorStoreId)->copy()->startOfMonth()->toDateString();
        }
        if (! is_string($ate) || $ate === '') {
            $ate = StoreBusinessTime::nowForStore($anchorStoreId)->copy()->endOfMonth()->toDateString();
        }

        $startLocal = Carbon::createFromFormat('Y-m-d', $desde, $tz)->startOfDay();
        $endLocal = Carbon::createFromFormat('Y-m-d', $ate, $tz)->startOfDay();
        if ($endLocal->lt($startLocal)) {
            [$startLocal, $endLocal] = [$endLocal, $startLocal];
        }

        $byDay = [];
        $cursor = $startLocal->copy();
        while ($cursor->lte($endLocal)) {
            $key = $cursor->toDateString();
            $byDay[$key] = 0;
            $cursor->addDay();
        }

        $sentAts = $this->reportQuery($storeIds, $filters)
            ->orderBy('sent_at')
            ->pluck('sent_at');

        foreach ($sentAts as $sentAt) {
            if ($sentAt === null) {
                continue;
            }
            $dayKey = Carbon::parse($sentAt)->timezone($tz)->toDateString();
            if (! array_key_exists($dayKey, $byDay)) {
                continue;
            }
            $byDay[$dayKey]++;
        }

        $dayCount = count($byDay);
        $bucketDays = $this->chartBucketDays($dayCount);
        $unitEur = $this->estimateCost(1)['unit_eur'];

        $labels = [];
        $dates = [];
        $counts = [];
        $costs = [];
        $totalCount = 0;

        $dayEntries = array_values(array_map(
            static fn (string $date, int $count): array => ['date' => $date, 'count' => $count],
            array_keys($byDay),
            array_values($byDay),
        ));

        for ($i = 0, $n = count($dayEntries); $i < $n; $i += $bucketDays) {
            $chunk = array_slice($dayEntries, $i, $bucketDays);
            $chunkCount = 0;
            foreach ($chunk as $entry) {
                $chunkCount += (int) $entry['count'];
            }
            $firstDate = (string) $chunk[0]['date'];
            $lastDate = (string) $chunk[count($chunk) - 1]['date'];
            $firstLabel = Carbon::createFromFormat('Y-m-d', $firstDate, $tz)->format('d/m');
            $lastLabel = Carbon::createFromFormat('Y-m-d', $lastDate, $tz)->format('d/m');

            $dates[] = $firstDate;
            $labels[] = $bucketDays === 1 || $firstDate === $lastDate
                ? $firstLabel
                : $firstLabel.'–'.$lastLabel;
            $counts[] = $chunkCount;
            $costs[] = round($chunkCount * $unitEur, 2);
            $totalCount += $chunkCount;
        }

        return [
            'labels' => $labels,
            'dates' => $dates,
            'counts' => $counts,
            'costs_eur' => $costs,
            'total_count' => $totalCount,
            'total_cost_eur' => round($totalCount * $unitEur, 2),
            'bucket_days' => $bucketDays,
        ];
    }

    /** Agrupa barras quando o período é longo (1 = diário, 5 ou 10 = buckets). */
    public function chartBucketDays(int $dayCount): int
    {
        if ($dayCount > 120) {
            return 10;
        }
        if ($dayCount > 45) {
            return 5;
        }

        return 1;
    }

    /**
     * @param  list<int>  $storeIds
     * @return list<int>
     */
    private function normalizeStoreIds(array $storeIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $storeIds)));
        if ($ids === []) {
            return [(int) current_store_id()];
        }

        return $ids;
    }
}
