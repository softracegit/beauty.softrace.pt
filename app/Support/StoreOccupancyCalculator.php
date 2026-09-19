<?php

namespace App\Support;

use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\CalendarEventService;
use App\Models\CalendarEventServiceExtra;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Ocupação por loja (mesma lógica do dashboard Resumo/Ocupação).
 */
final class StoreOccupancyCalculator
{
    public const LUNCH_BREAK_MINUTES = 60;

    /**
     * @return array{
     *   taxa_ocupacao: float,
     *   minutos_preenchidos: float,
     *   minutos_uteis: int,
     *   horas_preenchidas: string,
     *   horas_uteis: string,
     *   num_tecnicos: int
     * }
     */
    public function forStore(Store $store, Carbon $startLocal, Carbon $endLocal): array
    {
        $agents = Agent::query()
            ->forStore((int) $store->id)
            ->where('status', Agent::STATUS_ACTIVE)
            ->whereHas('user', fn ($q) => $q->whereIn('role', User::serviceProviderRoles()))
            ->get(['id', 'user_id', 'name', 'weekly_schedule']);

        $userIds = $agents->pluck('user_id')->filter()->map(fn ($id) => (int) $id)->values();
        $schedule = $store->normalizedWeeklySchedule();
        $capacity = $this->capacityMinutes($startLocal, $endLocal, $agents, $schedule, true);
        $filled = $this->filledMinutes((int) $store->id, $startLocal, $endLocal, $userIds);

        $taxa = $capacity > 0 ? round(min(100, ($filled / $capacity) * 100), 1) : 0.0;

        return [
            'taxa_ocupacao' => $taxa,
            'minutos_preenchidos' => $filled,
            'minutos_uteis' => $capacity,
            'horas_preenchidas' => $this->formatDuration((int) round($filled)),
            'horas_uteis' => $this->formatDuration($capacity),
            'num_tecnicos' => $agents->count(),
        ];
    }

    /**
     * @param  Collection<int, Agent>  $agents
     * @param  array<string, mixed>  $storeSchedule
     */
    public function capacityMinutes(
        Carbon $start,
        Carbon $end,
        Collection $agents,
        array $storeSchedule,
        bool $subtractLunch = false,
    ): int {
        if ($agents->isEmpty()) {
            return 0;
        }

        $total = 0;
        $d = $start->copy()->startOfDay();
        $last = $end->copy()->startOfDay();
        while ($d->lte($last)) {
            $dayKey = WeeklyScheduleWindow::carbonIsoToWeekdayKey($d->dayOfWeekIso);
            foreach ($agents as $agent) {
                $window = WeeklyScheduleWindow::resolveMinutesWindow(
                    $agent->weekly_schedule,
                    $dayKey,
                    $storeSchedule
                );
                if ($window === null) {
                    continue;
                }
                $minutes = max(0, (int) $window[1] - (int) $window[0]);
                if ($subtractLunch && $minutes > 0) {
                    $minutes = max(0, $minutes - self::LUNCH_BREAK_MINUTES);
                }
                $total += $minutes;
            }
            $d->addDay();
        }

        return $total;
    }

    /**
     * @param  Collection<int, int>  $prestadorUserIds
     */
    public function filledMinutes(int $storeId, Carbon $startLocal, Carbon $endLocal, Collection $prestadorUserIds): float
    {
        if ($prestadorUserIds->isEmpty()) {
            return 0.0;
        }

        $tz = StoreBusinessTime::timezoneForStore($storeId);
        $startUtc = $startLocal->copy()->timezone($tz)->startOfDay()->utc();
        $endUtc = $endLocal->copy()->timezone($tz)->endOfDay()->utc();

        $eventIds = CalendarEvent::query()
            ->where('store_id', $storeId)
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->whereIn('user_id', $prestadorUserIds->all())
            ->whereBetween('start_at', [$startUtc, $endUtc])
            ->pluck('id');

        if ($eventIds->isEmpty()) {
            return 0.0;
        }

        $totalMinutes = (int) CalendarEventService::query()->whereIn('calendar_event_id', $eventIds)->sum('duration');
        $cesIds = CalendarEventService::query()->whereIn('calendar_event_id', $eventIds)->pluck('id');
        $extraMinutes = $cesIds->isEmpty()
            ? 0
            : (int) CalendarEventServiceExtra::query()->whereIn('calendar_event_service_id', $cesIds)->sum('duration');

        return (float) ($totalMinutes + $extraMinutes);
    }

    public function formatDuration(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0h';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        if ($m === 0) {
            return $h.'h';
        }
        if ($h === 0) {
            return $m.'m';
        }

        return $h.'h '.$m.'m';
    }
}
