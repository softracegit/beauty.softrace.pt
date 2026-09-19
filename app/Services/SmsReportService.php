<?php

namespace App\Services;

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
     */
    public function reportQuery(array $storeIds, int $year, int $month): Builder
    {
        $storeIds = $this->normalizeStoreIds($storeIds);
        $anchorStoreId = $storeIds[0];
        $today = StoreBusinessTime::nowForStore($anchorStoreId)->startOfDay();
        $year = max($this->minYear($storeIds), min($today->year, $year));
        $month = max(1, min(12, $month));
        if ($year === $today->year && $month > $today->month) {
            $month = $today->month;
        }

        $start = Carbon::create($year, $month, 1, 0, 0, 0, $today->timezoneName)->startOfMonth()->utc();
        $end = Carbon::create($year, $month, 1, 0, 0, 0, $today->timezoneName)->endOfMonth()->endOfDay()->utc();

        return SmsMessage::query()
            ->whereIn('store_id', $storeIds)
            ->whereBetween('sent_at', [$start, $end])
            ->with(['client:id,name']);
    }

    /**
     * @return array<int, string>
     */
    public function monthOptions(): array
    {
        return [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
        ];
    }

    /**
     * @param  list<int>  $storeIds
     * @return array<int, int>
     */
    public function availableYears(array $storeIds): array
    {
        $storeIds = $this->normalizeStoreIds($storeIds);
        $today = StoreBusinessTime::nowForStore($storeIds[0]);

        return range($this->minYear($storeIds), $today->year);
    }

    public function periodLabel(int $year, int $month, ?int $timezoneStoreId = null): string
    {
        $tz = StoreBusinessTime::timezoneForStore($timezoneStoreId ?? current_store_id());

        return Carbon::create($year, $month, 1, 0, 0, 0, $tz)
            ->locale('pt')
            ->translatedFormat('F Y');
    }

    /**
     * @param  list<int>  $storeIds
     * @return Collection<int, object{month: int, count: int}>
     */
    public function countsByTypeForPeriod(array $storeIds, int $year, int $month): Collection
    {
        return $this->reportQuery($storeIds, $year, $month)
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->orderBy('type')
            ->get();
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

    /**
     * @param  list<int>  $storeIds
     */
    private function minYear(array $storeIds): int
    {
        $storeIds = $this->normalizeStoreIds($storeIds);
        $anchorStoreId = $storeIds[0];
        $first = SmsMessage::query()
            ->whereIn('store_id', $storeIds)
            ->orderBy('sent_at')
            ->value('sent_at');

        if ($first === null) {
            return StoreBusinessTime::nowForStore($anchorStoreId)->year;
        }

        return StoreBusinessTime::toUtcInstant($first)
            ?->timezone(StoreBusinessTime::timezoneForStore($anchorStoreId))
            ->year ?? StoreBusinessTime::nowForStore($anchorStoreId)->year;
    }
}
