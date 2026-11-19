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
