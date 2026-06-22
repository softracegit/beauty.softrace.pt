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
    private const EXCLUDED_STATUSES = [
        CalendarEvent::STATUS_CANCELADO,
        CalendarEvent::STATUS_ANULADO,
    ];

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
        $nowUtc = StoreBusinessTime::nowUtcForStore($storeId);

        $base = fn () => $this->baseQuery($storeId, $userId);

        $marcacoesHoje = (clone $base())
            ->whereBetween('start_at', $this->utcBounds($startOfDay, $endOfDay))
            ->count();

        $marcacoesEsteMes = (clone $base())
            ->whereBetween('start_at', $this->utcBounds($startOfMonth, $endOfMonth))
            ->count();

        $marcacoesMesPorRealizar = (clone $base())
            ->whereBetween('start_at', $this->utcBounds($startOfMonth, $endOfMonth))
            ->whereNotIn('status', array_merge(self::EXCLUDED_STATUSES, self::COMPLETED_STATUSES, [CalendarEvent::STATUS_FALTOU]))
            ->count();

        $clientesAtendidosMes = (int) (clone $base())
            ->whereBetween('start_at', $this->utcBounds($startOfMonth, $endOfMonth))
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereNotNull('client_id')
            ->distinct('client_id')
            ->count('client_id');

        $marcacoesEstaSemana = (clone $base())
            ->whereBetween('start_at', $this->utcBounds($startOfWeek, $endOfWeek))
            ->count();

        $marcacoesConcluidasHoje = (clone $base())
            ->whereBetween('start_at', $this->utcBounds($startOfDay, $endOfDay))
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->count();

        $faltasEsteMes = (clone $base())
            ->whereBetween('start_at', $this->utcBounds($startOfMonth, $endOfMonth))
            ->where('status', CalendarEvent::STATUS_FALTOU)
            ->count();

        $proximasMarcacoesHoje = (clone $base())
            ->where('start_at', '>=', $nowUtc)
            ->where('start_at', '<=', $this->utcBounds($startOfDay, $endOfDay)[1])
            ->whereNotIn('status', array_merge(self::EXCLUDED_STATUSES, self::COMPLETED_STATUSES, [CalendarEvent::STATUS_FALTOU]))
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
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->whereNotIn('calendar_events.status', self::EXCLUDED_STATUSES)
            ->whereBetween('calendar_events.start_at', $this->utcBounds($startOfMonth, $endOfMonth));

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
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->whereNotIn('status', self::EXCLUDED_STATUSES);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function utcBounds(Carbon $startLocal, Carbon $endLocal): array
    {
        return [
            StoreBusinessTime::toUtcInstant($startLocal),
            StoreBusinessTime::toUtcInstant($endLocal),
        ];
    }

    private function horasAgendadasEntre(int $storeId, ?int $userId, Carbon $startLocal, Carbon $endLocal): float
    {
        $eventIds = $this->baseQuery($storeId, $userId)
            ->whereBetween('start_at', $this->utcBounds($startLocal, $endLocal))
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
