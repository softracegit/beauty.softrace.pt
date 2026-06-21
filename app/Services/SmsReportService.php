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
     * @return array{today: int, week: int, month: int}
     */
    public function summaryCounts(int $storeId): array
    {
        $now = StoreBusinessTime::nowForStore($storeId);
        $todayStart = $now->copy()->startOfDay()->utc();
        $todayEnd = $now->copy()->endOfDay()->utc();
        $weekStart = $now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay()->utc();
        $monthStart = $now->copy()->startOfMonth()->startOfDay()->utc();

        $base = SmsMessage::query()->forStore($storeId);

        return [
            'today' => (clone $base)->whereBetween('sent_at', [$todayStart, $todayEnd])->count(),
            'week' => (clone $base)->where('sent_at', '>=', $weekStart)->where('sent_at', '<=', $todayEnd)->count(),
            'month' => (clone $base)->where('sent_at', '>=', $monthStart)->where('sent_at', '<=', $todayEnd)->count(),
        ];
    }

    public function reportQuery(int $storeId, int $year, int $month): Builder
    {
        $today = StoreBusinessTime::nowForStore($storeId)->startOfDay();
        $year = max($this->minYear($storeId), min($today->year, $year));
        $month = max(1, min(12, $month));
        if ($year === $today->year && $month > $today->month) {
            $month = $today->month;
        }

        $start = Carbon::create($year, $month, 1, 0, 0, 0, $today->timezoneName)->startOfMonth()->utc();
        $end = Carbon::create($year, $month, 1, 0, 0, 0, $today->timezoneName)->endOfMonth()->endOfDay()->utc();

        return SmsMessage::query()
            ->forStore($storeId)
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
     * @return array<int, int>
     */
    public function availableYears(int $storeId): array
    {
        $today = StoreBusinessTime::nowForStore($storeId);

        return range($this->minYear($storeId), $today->year);
    }

    public function periodLabel(int $year, int $month): string
    {
        $tz = StoreBusinessTime::timezoneForStore(current_store_id());

        return Carbon::create($year, $month, 1, 0, 0, 0, $tz)
            ->locale('pt')
            ->translatedFormat('F Y');
    }

    /**
     * @return Collection<int, object{month: int, count: int}>
     */
    public function countsByTypeForPeriod(int $storeId, int $year, int $month): Collection
    {
        return $this->reportQuery($storeId, $year, $month)
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->orderBy('type')
            ->get();
    }

    private function minYear(int $storeId): int
    {
        $first = SmsMessage::query()
            ->forStore($storeId)
            ->orderBy('sent_at')
            ->value('sent_at');

        if ($first === null) {
            return StoreBusinessTime::nowForStore($storeId)->year;
        }

        return StoreBusinessTime::toUtcInstant($first)
            ?->timezone(StoreBusinessTime::timezoneForStore($storeId))
            ->year ?? StoreBusinessTime::nowForStore($storeId)->year;
    }
}
