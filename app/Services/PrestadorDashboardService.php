<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\CalendarEventService;
use App\Models\User;
use App\Support\StoreBusinessTime;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PrestadorDashboardService
{
    /** @var list<string> */
    private const COMPLETED_STATUSES = [
        CalendarEvent::STATUS_COMPLETO,
        CalendarEvent::STATUS_TERMINADO,
    ];

    /**
     * @return array{
     *   agentName: string,
     *   dashboardSubtitle: string,
     *   storeScope: bool,
     *   marcacoesHoje: int,
     *   marcacoesEsteMes: int,
     *   marcacoesMesPorRealizar: int,
     *   clientesAtendidosMes: int,
     *   marcacoesEstaSemana: int,
     *   marcacoesConcluidasHoje: int,
     *   horasAgendadasHoje: float,
     *   faltasEsteMes: int,
     *   proximasMarcacoesHoje: Collection<int, CalendarEvent>,
     *   servicosMaisRealizados: Collection<int, object{service_name: string, total: int}>,
     *   periodoMesLabel: string,
     * }
     */
    public function build(User $user, int $storeId): array
    {
        $user->loadMissing('agent');

        return $this->buildMetrics($storeId, (int) $user->id, [
            'agentName' => $user->agent?->name ?? $user->name ?? 'Prestador(a)',
            'dashboardSubtitle' => 'Resumo das suas marcações',
            'storeScope' => false,
        ]);
    }

    /**
     * @return array{
     *   agentName: string,
     *   dashboardSubtitle: string,
     *   storeScope: bool,
     *   marcacoesHoje: int,
     *   marcacoesEsteMes: int,
     *   marcacoesMesPorRealizar: int,
     *   clientesAtendidosMes: int,
     *   marcacoesEstaSemana: int,
     *   marcacoesConcluidasHoje: int,
     *   horasAgendadasHoje: float,
     *   faltasEsteMes: int,
     *   proximasMarcacoesHoje: Collection<int, CalendarEvent>,
     *   servicosMaisRealizados: Collection<int, object{service_name: string, total: int}>,
     *   periodoMesLabel: string,
     * }
     */
    public function buildForStore(int $storeId, ?User $viewer = null): array
    {
        return $this->buildMetrics($storeId, null, [
            'agentName' => $viewer?->name ?? 'Receção',
            'dashboardSubtitle' => 'Resumo da loja',
            'storeScope' => true,
        ]);
    }

    /**
     * @param  array{agentName: string, dashboardSubtitle: string, storeScope: bool}  $meta
     * @return array{
     *   agentName: string,
     *   dashboardSubtitle: string,
     *   storeScope: bool,
     *   marcacoesHoje: int,
     *   marcacoesEsteMes: int,
     *   marcacoesMesPorRealizar: int,
     *   clientesAtendidosMes: int,
     *   marcacoesEstaSemana: int,
     *   marcacoesConcluidasHoje: int,
     *   horasAgendadasHoje: float,
     *   faltasEsteMes: int,
     *   proximasMarcacoesHoje: Collection<int, CalendarEvent>,
     *   servicosMaisRealizados: Collection<int, object{service_name: string, total: int}>,
     *   periodoMesLabel: string,
     * }
     */
    private function buildMetrics(int $storeId, ?int $userId, array $meta): array
    {
        $today = StoreBusinessTime::nowForStore($storeId);
        $startOfDay = $today->copy()->startOfDay();
        $endOfDay = $today->copy()->endOfDay();
        $startOfWeek = $today->copy()->startOfWeek();
        $endOfWeek = $today->copy()->endOfWeek();
        $startOfMonth = $today->copy()->startOfMonth();
        $endOfMonth = $today->copy()->endOfMonth();
        $now = StoreBusinessTime::nowForStore($storeId);

        $base = fn () => $this->baseQuery($storeId, $userId);

        $marcacoesHoje = (clone $base())
            ->whereBetween('start_at', [$startOfDay, $endOfDay])
            ->count();

        $marcacoesEsteMes = (clone $base())
            ->whereBetween('start_at', [$startOfMonth, $endOfMonth])
            ->count();

        $marcacoesMesPorRealizar = (clone $base())
            ->whereBetween('start_at', [$startOfMonth, $endOfMonth])
            ->whereNotIn('status', array_merge(self::COMPLETED_STATUSES, [CalendarEvent::STATUS_FALTOU]))
            ->count();

        $clientesAtendidosMes = (int) (clone $base())
            ->whereBetween('start_at', [$startOfMonth, $endOfMonth])
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereNotNull('client_id')
            ->distinct('client_id')
            ->count('client_id');

        $marcacoesEstaSemana = (clone $base())
            ->whereBetween('start_at', [$startOfWeek, $endOfWeek])
            ->count();

        $marcacoesConcluidasHoje = $this->applyAlreadyPassedToday(
            (clone $base())
                ->whereBetween('start_at', [$startOfDay, $endOfDay])
                ->where('status', '!=', CalendarEvent::STATUS_FALTOU),
            $now,
        )->count();

        $faltasEsteMes = (clone $base())
            ->whereBetween('start_at', [$startOfMonth, $endOfMonth])
            ->where('status', CalendarEvent::STATUS_FALTOU)
            ->count();

        $proximasMarcacoesHoje = (clone $base())
            ->where('start_at', '>=', $now)
            ->where('start_at', '<=', $endOfDay)
            ->whereNotIn('status', array_merge(self::COMPLETED_STATUSES, [CalendarEvent::STATUS_FALTOU]))
            ->with(['client', 'eventServices', 'user.agent'])
            ->orderBy('start_at')
            ->limit(12)
            ->get();

        $horasAgendadasHoje = $this->horasAgendadasEntre(
            $storeId,
            $userId,
            $startOfDay,
            $endOfDay,
        );

        $servicosQuery = CalendarEventService::query()
            ->join('calendar_events', 'calendar_event_services.calendar_event_id', '=', 'calendar_events.id')
            ->join('services', 'calendar_event_services.service_id', '=', 'services.id')
            ->where('calendar_events.store_id', $storeId)
            ->where('services.store_id', $storeId)
            ->whereNull('calendar_events.personal_time_type_id')
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->whereNotIn('calendar_events.status', CalendarEvent::dashboardExcludedStatuses())
            ->whereBetween('calendar_events.start_at', [$startOfMonth, $endOfMonth]);

        if ($userId !== null) {
            $servicosQuery->where('calendar_events.user_id', $userId);
        }

        $servicosMaisRealizados = $servicosQuery
            ->groupBy('services.id', 'services.name')
            ->selectRaw('services.name as service_name, count(*) as total')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return array_merge($meta, [
            'marcacoesHoje' => $marcacoesHoje,
            'marcacoesEsteMes' => $marcacoesEsteMes,
            'marcacoesMesPorRealizar' => $marcacoesMesPorRealizar,
            'clientesAtendidosMes' => $clientesAtendidosMes,
            'marcacoesEstaSemana' => $marcacoesEstaSemana,
            'marcacoesConcluidasHoje' => $marcacoesConcluidasHoje,
            'horasAgendadasHoje' => $horasAgendadasHoje,
            'faltasEsteMes' => $faltasEsteMes,
            'proximasMarcacoesHoje' => $proximasMarcacoesHoje,
            'servicosMaisRealizados' => $servicosMaisRealizados,
            'periodoMesLabel' => $startOfMonth->locale('pt_PT')->translatedFormat('F Y'),
        ]);
    }

    /**
     * @return Builder<CalendarEvent>
     */
    private function baseQuery(int $storeId, ?int $userId = null): Builder
    {
        $query = CalendarEvent::forStore($storeId)
            ->countableForDashboard();

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query;
    }

    /**
     * Marcações de hoje cujo horário já passou (fim ≤ agora, ou início < agora sem fim).
     *
     * @param  Builder<CalendarEvent>  $query
     * @return Builder<CalendarEvent>
     */
    private function applyAlreadyPassedToday(Builder $query, Carbon $now): Builder
    {
        return $query->where(function (Builder $q) use ($now) {
            $q->where(function (Builder $q2) use ($now) {
                $q2->whereNotNull('end_at')->where('end_at', '<=', $now);
            })->orWhere(function (Builder $q2) use ($now) {
                $q2->whereNull('end_at')->where('start_at', '<', $now);
            });
        });
    }

    private function horasAgendadasEntre(int $storeId, ?int $userId, Carbon $startLocal, Carbon $endLocal): float
    {
        $eventIds = $this->baseQuery($storeId, $userId)
            ->whereBetween('start_at', [$startLocal, $endLocal])
            ->pluck('id');

        if ($eventIds->isEmpty()) {
            return 0.0;
        }

        $minutes = (int) CalendarEventService::query()
            ->whereIn('calendar_event_id', $eventIds)
            ->sum('duration');

        return round($minutes / 60, 1);
    }
}
