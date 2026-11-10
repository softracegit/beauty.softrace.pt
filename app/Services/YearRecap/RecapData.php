<?php

namespace App\Services\YearRecap;

use App\Models\CalendarEvent;
use App\Models\Sale;
use App\Models\User;
use App\Services\FinancialDashboardService;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Consultas do recap, partilhadas entre slides e feitas uma só vez (memo).
 *
 * "Marcação realizada" = marcação da agenda (sem tempo pessoal), não cancelada,
 * não anulada e sem falta, com início dentro do período.
 */
final class RecapData
{
    /** @var array<string, mixed> */
    private array $memo = [];

    public function __construct(private readonly RecapContext $ctx) {}

    /**
     * Marcações realizadas no ano (no âmbito do contexto).
     *
     * @return Collection<int, object{id: int, start: Carbon, minutes: int, client_id: ?int, user_id: ?int, online: bool}>
     */
    public function events(): Collection
    {
        return $this->remember('events', function (): Collection {
            $tz = $this->ctx->timezone;

            return $this->eventsQuery($this->ctx->startUtc(), $this->ctx->endUtc())
                ->select(['id', 'start_at', 'end_at', 'client_id', 'user_id', 'marcacao_source'])
                ->orderBy('start_at')
                ->get()
                ->map(function (object $row) use ($tz): object {
                    $start = Carbon::parse($row->start_at, 'UTC')->setTimezone($tz);
                    $minutes = 0;
                    if ($row->end_at !== null) {
                        $minutes = (int) round(Carbon::parse($row->end_at, 'UTC')->diffInMinutes(Carbon::parse($row->start_at, 'UTC'), true));
                        // Durações absurdas (eventos esquecidos abertos) não contam para as horas.
                        $minutes = $minutes > 0 && $minutes <= 12 * 60 ? $minutes : 0;
                    }

                    return (object) [
                        'id' => (int) $row->id,
                        'start' => $start,
                        'minutes' => $minutes,
                        'client_id' => $row->client_id !== null ? (int) $row->client_id : null,
                        'user_id' => $row->user_id !== null ? (int) $row->user_id : null,
                        'online' => $row->marcacao_source === 'online',
                    ];
                });
        });
    }

    public function previousYearCount(): int
    {
        return $this->remember('previousYearCount', fn (): int => $this->eventsQuery(
            $this->ctx->previousStartUtc(),
            $this->ctx->previousEndUtc(),
        )->count());
    }

    /**
     * Visitas por cliente no ano.
     *
     * @return Collection<int, int> client_id => visitas (ordenado desc)
     */
    public function visitsByClient(): Collection
    {
        return $this->remember('visitsByClient', fn (): Collection => $this->events()
            ->whereNotNull('client_id')
            ->countBy('client_id')
            ->sortDesc());
    }

    /** Clientes deste ano que já tinham vindo antes (à loja, ou à própria técnica). */
    public function returningClientsCount(): int
    {
        return $this->remember('returningClientsCount', function (): int {
            $ids = $this->visitsByClient()->keys();
            $count = 0;
            foreach ($ids->chunk(1000) as $chunk) {
                $count += (int) $this->eventsQuery(null, $this->ctx->startUtc())
                    ->whereIn('client_id', $chunk->all())
                    ->distinct()
                    ->count('client_id');
            }

            return $count;
        });
    }

    /**
     * Serviços feitos no ano (uma linha por serviço dentro de cada marcação).
     *
     * @return Collection<int, object{service: string, category: ?string, qtd: int}>
     */
    public function serviceCounts(): Collection
    {
        return $this->remember('serviceCounts', function (): Collection {
            $query = DB::table('calendar_event_services')
                ->join('calendar_events', 'calendar_events.id', '=', 'calendar_event_services.calendar_event_id')
                ->join('services', 'services.id', '=', 'calendar_event_services.service_id')
                ->leftJoin('categories', 'categories.id', '=', 'services.category_id');
            $this->applyEventFilters($query, $this->ctx->startUtc(), $this->ctx->endUtc());

            return $query
                ->groupBy('services.id', 'services.name', 'categories.name')
                ->selectRaw('services.name as service, categories.name as category, count(*) as qtd')
                ->orderByDesc('qtd')
                ->get()
                ->map(fn (object $r): object => (object) [
                    'service' => (string) $r->service,
                    'category' => $r->category !== null ? (string) $r->category : null,
                    'qtd' => (int) $r->qtd,
                ]);
        });
    }

    /** @param  list<int>  $ids @return array<int, string> */
    public function clientNames(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('clients')->whereIn('id', $ids)->pluck('name', 'id')
            ->map(fn ($n) => trim((string) $n))
            ->all();
    }

    /** @param  list<int>  $ids @return array<int, string> Nome de agenda (agents.name) ou nome do utilizador. */
    public function staffNames(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $agentNames = DB::table('agents')
            ->where('store_id', $this->ctx->storeId)
            ->whereIn('user_id', $ids)
            ->pluck('name', 'user_id');
        $userNames = DB::table('users')->whereIn('id', $ids)->pluck('name', 'id');

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = trim((string) ($agentNames[$id] ?? $userNames[$id] ?? ''));
        }

        return $out;
    }

    /** Ids de utilizadores que são prestadores (para o ranking da equipa). */
    public function serviceProviderIds(): array
    {
        return $this->remember('serviceProviderIds', fn (): array => DB::table('users')
            ->whereIn('role', User::serviceProviderRoles())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all());
    }

    /** KPIs financeiros do ano (mesmas regras do dashboard financeiro). */
    public function financial(): array
    {
        return $this->remember('financial', fn (): array => app(FinancialDashboardService::class)
            ->build($this->ctx->storeId, $this->ctx->year, FinancialDashboardService::MONTH_WHOLE_YEAR));
    }

    /**
     * Pagamentos recebidos no ano, por método.
     *
     * @return Collection<int, object{method: string, qtd: int, total: float}>
     */
    public function paymentsByMethod(): Collection
    {
        return $this->remember('paymentsByMethod', fn (): Collection => DB::table('sales')
            ->where('store_id', $this->ctx->storeId)
            ->where('status', Sale::STATUS_PAGO)
            ->whereDate('data_emissao', '>=', $this->ctx->start->toDateString())
            ->whereDate('data_emissao', '<=', $this->ctx->end->toDateString())
            ->groupBy('payment_method')
            ->selectRaw('payment_method as method, count(*) as qtd, sum(total) as total')
            ->orderByDesc('qtd')
            ->get()
            ->map(fn (object $r): object => (object) [
                'method' => (string) ($r->method ?? ''),
                'qtd' => (int) $r->qtd,
                'total' => (float) $r->total,
            ]));
    }

    /** @return Collection<string, int> tipo => quantidade */
    public function smsByType(): Collection
    {
        return $this->remember('smsByType', fn (): Collection => DB::table('sms_messages')
            ->where('store_id', $this->ctx->storeId)
            ->whereBetween('sent_at', [$this->fmt($this->ctx->startUtc()), $this->fmt($this->ctx->endUtc())])
            ->groupBy('type')
            ->selectRaw('type, count(*) as qtd')
            ->orderByDesc('qtd')
            ->pluck('qtd', 'type')
            ->map(fn ($n) => (int) $n));
    }

    /**
     * Marcações criadas na agenda pelo próprio utilizador (registo de atividade).
     * O registo de atividade existe desde março de 2026.
     */
    public function marcacoesCreatedByViewer(): int
    {
        return $this->remember('createdByViewer', fn (): int => (int) DB::table('activity_log')
            ->where('store_id', $this->ctx->storeId)
            ->where('event', 'created')
            ->where('subject_type', (new CalendarEvent)->getMorphClass())
            ->where('causer_type', $this->ctx->viewer->getMorphClass())
            ->where('causer_id', $this->ctx->viewer->id)
            ->where('description', 'like', 'Marcação criada (agenda)%')
            ->whereBetween('created_at', [$this->fmt($this->ctx->startUtc()), $this->fmt($this->ctx->endUtc())])
            ->count());
    }

    /** Total de marcações criadas na agenda por qualquer pessoa da equipa (para a percentagem). */
    public function marcacoesCreatedByStaff(): int
    {
        return $this->remember('createdByStaff', fn (): int => (int) DB::table('activity_log')
            ->where('store_id', $this->ctx->storeId)
            ->where('event', 'created')
            ->where('subject_type', (new CalendarEvent)->getMorphClass())
            ->where('causer_type', $this->ctx->viewer->getMorphClass())
            ->where('description', 'like', 'Marcação criada (agenda)%')
            ->whereBetween('created_at', [$this->fmt($this->ctx->startUtc()), $this->fmt($this->ctx->endUtc())])
            ->count());
    }

    private function eventsQuery(?Carbon $fromUtc, ?Carbon $toUtc): Builder
    {
        $query = DB::table('calendar_events');
        $this->applyEventFilters($query, $fromUtc, $toUtc);

        return $query;
    }

    private function applyEventFilters(Builder $query, ?Carbon $fromUtc, ?Carbon $toUtc): void
    {
        $query
            ->where('calendar_events.store_id', $this->ctx->storeId)
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->whereNull('calendar_events.personal_time_type_id')
            ->whereNotIn('calendar_events.status', [
                CalendarEvent::STATUS_CANCELADO,
                CalendarEvent::STATUS_ANULADO,
                CalendarEvent::STATUS_FALTOU,
            ]);

        if ($fromUtc !== null) {
            $query->where('calendar_events.start_at', '>=', $this->fmt($fromUtc));
        }
        if ($toUtc !== null) {
            // Limite superior: fim do período (inclusivo) ou início do ano (exclusivo, para "já cá tinham vindo").
            $operator = $fromUtc === null ? '<' : '<=';
            $query->where('calendar_events.start_at', $operator, $this->fmt($toUtc));
        }

        $userId = $this->ctx->subjectUserId();
        if ($userId !== null) {
            $query->where('calendar_events.user_id', $userId);
        }
    }

    private function fmt(Carbon $utc): string
    {
        return $utc->copy()->utc()->format('Y-m-d H:i:s');
    }

    /**
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T
     */
    private function remember(string $key, callable $fn): mixed
    {
        if (! array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $fn();
        }

        return $this->memo[$key];
    }
}
