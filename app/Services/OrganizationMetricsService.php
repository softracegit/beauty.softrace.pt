<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\CashRegisterSession;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SmsMessage;
use App\Models\Store;
use App\Models\User;
use App\Support\CurrentStore;
use App\Support\MarcacaoMoneyBatch;
use App\Support\StoreBusinessTime;
use App\Support\StoreOccupancyCalculator;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrganizationMetricsService
{
    public function __construct(
        private readonly StoreOccupancyCalculator $occupancyCalculator,
        private readonly ComissoesReportService $comissoesReportService,
        private readonly CashRegisterService $cashRegisterService,
    ) {}

    /**
     * @param  list<int>  $storeIds
     * @param  'diaria'|'semanal'|null  $evolucaoMode
     * @return array<string, mixed>
     */
    public function summarize(array $storeIds, string $desde, string $ate, ?string $evolucaoMode = null): array
    {
        $storeIds = array_values(array_unique(array_map('intval', $storeIds)));
        [$prevDesde, $prevAte] = $this->previousPeriodBounds($desde, $ate);
        $evolucaoMode = $this->resolveEvolutionMode($desde, $ate, $evolucaoMode);

        $empty = $this->emptyPayload($desde, $ate, $prevDesde, $prevAte, $evolucaoMode);
        if ($storeIds === []) {
            return $empty;
        }

        $stores = Store::query()->whereIn('id', $storeIds)->orderBy('name')->get()->keyBy('id');

        $porLoja = collect();
        $faturacaoTotal = 0.0;
        $faturacaoAnterior = 0.0;
        $numVendas = 0;
        $numVendasAnterior = 0;
        $previstoTotal = 0.0;
        $porFazerTotal = 0.0;
        $marcacoesTotal = 0;
        $marcacoesCompletas = 0;
        $faltouTotal = 0;
        $canceladoTotal = 0;
        $anuladoTotal = 0;
        $clientIdsActual = [];
        $clientIdsAnterior = [];
        $minutosPreenchidosTotal = 0.0;
        $minutosUteisTotal = 0;
        $rascunhosTotal = 0;
        $smsFalhasTotal = 0;
        $lojasCaixaAberta = 0;
        $servicosAgg = [];
        $categoriasAgg = [];
        $tecnicosAgg = [];
        $evolucaoPorLoja = [];

        foreach ($stores as $storeId => $store) {
            $storeId = (int) $storeId;
            /** @var Store $store */
            $range = $this->utcRangeForStore($storeId, $desde, $ate);
            $prevRange = $this->utcRangeForStore($storeId, $prevDesde, $prevAte);
            $tz = StoreBusinessTime::timezoneForStore($storeId);
            $startLocal = Carbon::parse($desde, $tz)->startOfDay();
            $endLocal = Carbon::parse($ate, $tz)->endOfDay();

            $vendas = $this->salesPaidInRange($storeId, $range);
            $vendasPrev = $this->salesPaidInRange($storeId, $prevRange);
            $pipeline = $this->pipelineInRange($storeId, $range);
            $marcacoes = $this->marcacoesCounts($storeId, $range);
            $clientIdsStore = $this->uniqueClientIdsAttended($storeId, $range);
            $clientIdsStorePrev = $this->uniqueClientIdsAttended($storeId, $prevRange);
            $ocupacao = $this->occupancyCalculator->forStore($store, $startLocal, $endLocal);
            $ops = $this->operationalStatus($store, $range);
            $topServicosLoja = $this->topServicosForStore($storeId, $range, 10);
            $topCategoriasLoja = $this->topCategoriasForStore($storeId, $range, 8);
            $comissoesLoja = $this->comissoesPorTecnicoForStore($store, $desde, $ate);

            $receita = round((float) ($vendas->receita ?? 0), 2);
            $vendasCnt = (int) ($vendas->cnt ?? 0);
            $receitaPrev = round((float) ($vendasPrev->receita ?? 0), 2);
            $vendasCntPrev = (int) ($vendasPrev->cnt ?? 0);
            $previsto = (float) $pipeline['previsto'];
            $porFazer = (float) $pipeline['por_fazer'];
            $marcTotal = (int) ($marcacoes->total ?? 0);
            $marcComp = (int) ($marcacoes->completas ?? 0);
            $faltou = (int) ($marcacoes->faltou ?? 0);
            $cancelado = (int) ($marcacoes->cancelado ?? 0);
            $anulado = (int) ($marcacoes->anulado ?? 0);
            $ticket = $vendasCnt > 0 ? round($receita / $vendasCnt, 2) : 0.0;
            $taxa = $marcTotal > 0 ? round(($marcComp / $marcTotal) * 100, 1) : 0.0;
            $noshows = $faltou + $cancelado + $anulado;
            $taxaNoshow = $marcTotal > 0 ? round(($noshows / $marcTotal) * 100, 1) : 0.0;
            $delta = round($receita - $receitaPrev, 2);
            $deltaPct = $this->deltaPct($receita, $receitaPrev);

            $faturacaoTotal += $receita;
            $faturacaoAnterior += $receitaPrev;
            $numVendas += $vendasCnt;
            $numVendasAnterior += $vendasCntPrev;
            $previstoTotal += $previsto;
            $porFazerTotal += $porFazer;
            $marcacoesTotal += $marcTotal;
            $marcacoesCompletas += $marcComp;
            $faltouTotal += $faltou;
            $canceladoTotal += $cancelado;
            $anuladoTotal += $anulado;
            $minutosPreenchidosTotal += $ocupacao['minutos_preenchidos'];
            $minutosUteisTotal += $ocupacao['minutos_uteis'];
            $rascunhosTotal += $ops['rascunhos'];
            $smsFalhasTotal += $ops['sms_falhados'];
            if ($ops['caixa_aberta']) {
                $lojasCaixaAberta++;
            }

            foreach ($clientIdsStore as $cid) {
                $clientIdsActual[(int) $cid] = true;
            }
            foreach ($clientIdsStorePrev as $cid) {
                $clientIdsAnterior[(int) $cid] = true;
            }

            foreach ($topServicosLoja as $svc) {
                $key = mb_strtolower(trim($svc->nome));
                if (! isset($servicosAgg[$key])) {
                    $servicosAgg[$key] = [
                        'nome' => $svc->nome,
                        'receita' => 0.0,
                        'qtd' => 0,
                        'por_loja' => [],
                    ];
                }
                $servicosAgg[$key]['receita'] += $svc->receita;
                $servicosAgg[$key]['qtd'] += $svc->qtd;
                $servicosAgg[$key]['por_loja'][] = (object) [
                    'store_id' => $storeId,
                    'loja' => $store->name,
                    'receita' => $svc->receita,
                    'qtd' => $svc->qtd,
                ];
            }

            foreach ($topCategoriasLoja as $cat) {
                $key = mb_strtolower(trim($cat->nome));
                if (! isset($categoriasAgg[$key])) {
                    $categoriasAgg[$key] = [
                        'nome' => $cat->nome,
                        'receita' => 0.0,
                        'qtd' => 0,
                        'por_loja' => [],
                    ];
                }
                $categoriasAgg[$key]['receita'] += $cat->receita;
                $categoriasAgg[$key]['qtd'] += $cat->qtd;
                $categoriasAgg[$key]['por_loja'][] = (object) [
                    'store_id' => $storeId,
                    'loja' => $store->name,
                    'receita' => $cat->receita,
                    'qtd' => $cat->qtd,
                ];
            }

            foreach ($comissoesLoja as $tec) {
                $uid = (int) $tec->user_id;
                if (! isset($tecnicosAgg[$uid])) {
                    $tecnicosAgg[$uid] = [
                        'user_id' => $uid,
                        'nome' => $tec->nome,
                        'comissao' => 0.0,
                        'por_loja' => [],
                    ];
                }
                $tecnicosAgg[$uid]['comissao'] += $tec->comissao;
                $tecnicosAgg[$uid]['por_loja'][] = (object) [
                    'store_id' => $storeId,
                    'loja' => $store->name,
                    'comissao' => $tec->comissao,
                ];
            }

            $porLoja->push((object) [
                'store_id' => $storeId,
                'nome' => $store->name,
                'faturacao' => $receita,
                'faturacao_anterior' => $receitaPrev,
                'faturacao_delta' => $delta,
                'faturacao_delta_pct' => $deltaPct,
                'num_vendas' => $vendasCnt,
                'ticket_medio' => $ticket,
                'previsto' => $previsto,
                'vendas_feitas' => $receita,
                'por_fazer' => $porFazer,
                'marcacoes' => $marcTotal,
                'marcacoes_completas' => $marcComp,
                'taxa_conclusao' => $taxa,
                'faltou' => $faltou,
                'cancelado' => $cancelado,
                'anulado' => $anulado,
                'noshows' => $noshows,
                'taxa_noshow' => $taxaNoshow,
                'clientes_unicos' => count($clientIdsStore),
                'taxa_ocupacao' => $ocupacao['taxa_ocupacao'],
                'horas_preenchidas' => $ocupacao['horas_preenchidas'],
                'horas_uteis' => $ocupacao['horas_uteis'],
                'ocupacao_label' => $ocupacao['horas_preenchidas'].' / '.$ocupacao['horas_uteis'],
                'caixa_aberta' => $ops['caixa_aberta'],
                'caixa_label' => $ops['caixa_label'],
                'rascunhos' => $ops['rascunhos'],
                'sms_falhados' => $ops['sms_falhados'],
            ]);

            $evolucaoPorLoja[] = [
                'store_id' => $storeId,
                'nome' => $store->name,
                'by_day' => $this->dailyRevenueMap($storeId, $range, $tz),
            ];
        }

        $faturacaoTotal = round($faturacaoTotal, 2);
        $faturacaoAnterior = round($faturacaoAnterior, 2);
        $taxaOcupacaoOrg = $minutosUteisTotal > 0
            ? round(min(100, ($minutosPreenchidosTotal / $minutosUteisTotal) * 100), 1)
            : 0.0;

        $topServicos = collect($servicosAgg)
            ->map(fn (array $row) => (object) [
                'nome' => $row['nome'],
                'receita' => round($row['receita'], 2),
                'qtd' => $row['qtd'],
                'por_loja' => collect($row['por_loja'])->sortByDesc('receita')->values(),
            ])
            ->sortByDesc('receita')
            ->take(10)
            ->values();

        $topCategorias = collect($categoriasAgg)
            ->map(fn (array $row) => (object) [
                'nome' => $row['nome'],
                'receita' => round($row['receita'], 2),
                'qtd' => $row['qtd'],
                'por_loja' => collect($row['por_loja'])->sortByDesc('receita')->values(),
            ])
            ->sortByDesc('receita')
            ->take(8)
            ->values();

        $comissoesTecnicos = collect($tecnicosAgg)
            ->map(fn (array $row) => (object) [
                'user_id' => $row['user_id'],
                'nome' => $row['nome'],
                'comissao' => round($row['comissao'], 2),
                'por_loja' => collect($row['por_loja'])->sortByDesc('comissao')->values(),
            ])
            ->sortByDesc('comissao')
            ->take(10)
            ->values();

        return [
            'desde' => $desde,
            'ate' => $ate,
            'prev_desde' => $prevDesde,
            'prev_ate' => $prevAte,
            'faturacao_total' => $faturacaoTotal,
            'faturacao_anterior' => $faturacaoAnterior,
            'faturacao_delta' => round($faturacaoTotal - $faturacaoAnterior, 2),
            'faturacao_delta_pct' => $this->deltaPct($faturacaoTotal, $faturacaoAnterior),
            'num_vendas' => $numVendas,
            'num_vendas_anterior' => $numVendasAnterior,
            'ticket_medio' => $numVendas > 0 ? round($faturacaoTotal / $numVendas, 2) : 0.0,
            'ticket_medio_anterior' => $numVendasAnterior > 0 ? round($faturacaoAnterior / $numVendasAnterior, 2) : 0.0,
            'previsto_total' => round($previstoTotal, 2),
            'vendas_feitas_total' => $faturacaoTotal,
            'por_fazer_total' => round($porFazerTotal, 2),
            'marcacoes_total' => $marcacoesTotal,
            'marcacoes_completas' => $marcacoesCompletas,
            'taxa_conclusao' => $marcacoesTotal > 0
                ? round(($marcacoesCompletas / $marcacoesTotal) * 100, 1)
                : 0.0,
            'faltou_total' => $faltouTotal,
            'cancelado_total' => $canceladoTotal,
            'anulado_total' => $anuladoTotal,
            'noshows_total' => $faltouTotal + $canceladoTotal + $anuladoTotal,
            'taxa_noshow' => $marcacoesTotal > 0
                ? round((($faltouTotal + $canceladoTotal + $anuladoTotal) / $marcacoesTotal) * 100, 1)
                : 0.0,
            'clientes_unicos' => count($clientIdsActual),
            'clientes_unicos_anterior' => count($clientIdsAnterior),
            'taxa_ocupacao' => $taxaOcupacaoOrg,
            'horas_preenchidas' => $this->occupancyCalculator->formatDuration((int) round($minutosPreenchidosTotal)),
            'horas_uteis' => $this->occupancyCalculator->formatDuration($minutosUteisTotal),
            'rascunhos_total' => $rascunhosTotal,
            'sms_falhados_total' => $smsFalhasTotal,
            'lojas_caixa_aberta' => $lojasCaixaAberta,
            'lojas_total' => $stores->count(),
            'top_servicos' => $topServicos,
            'top_categorias' => $topCategorias,
            'comissoes_tecnicos' => $comissoesTecnicos,
            'evolucao' => $this->buildEvolutionSeries($desde, $ate, $evolucaoPorLoja, $evolucaoMode),
            'por_loja' => $porLoja,
        ];
    }

    /**
     * @param  'diaria'|'semanal'|null  $forceMode
     */
    public function resolveEvolutionMode(string $desde, string $ate, ?string $forceMode = null): string
    {
        if (in_array($forceMode, ['diaria', 'semanal'], true)) {
            return $forceMode;
        }

        $days = Carbon::parse($desde)->startOfDay()->diffInDays(Carbon::parse($ate)->startOfDay()) + 1;

        return $days > 45 ? 'semanal' : 'diaria';
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function previousPeriodBounds(string $desde, string $ate): array
    {
        $start = Carbon::parse($desde)->startOfDay();
        $end = Carbon::parse($ate)->startOfDay();
        if ($end->lt($start)) {
            $end = $start->copy();
        }

        $days = $start->diffInDays($end) + 1;
        $prevAte = $start->copy()->subDay();
        $prevDesde = $prevAte->copy()->subDays($days - 1);

        return [$prevDesde->toDateString(), $prevAte->toDateString()];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(string $desde, string $ate, string $prevDesde, string $prevAte, string $evolucaoMode = 'diaria'): array
    {
        return [
            'desde' => $desde,
            'ate' => $ate,
            'prev_desde' => $prevDesde,
            'prev_ate' => $prevAte,
            'faturacao_total' => 0.0,
            'faturacao_anterior' => 0.0,
            'faturacao_delta' => 0.0,
            'faturacao_delta_pct' => null,
            'num_vendas' => 0,
            'num_vendas_anterior' => 0,
            'ticket_medio' => 0.0,
            'ticket_medio_anterior' => 0.0,
            'previsto_total' => 0.0,
            'vendas_feitas_total' => 0.0,
            'por_fazer_total' => 0.0,
            'marcacoes_total' => 0,
            'marcacoes_completas' => 0,
            'taxa_conclusao' => 0.0,
            'faltou_total' => 0,
            'cancelado_total' => 0,
            'anulado_total' => 0,
            'noshows_total' => 0,
            'taxa_noshow' => 0.0,
            'clientes_unicos' => 0,
            'clientes_unicos_anterior' => 0,
            'taxa_ocupacao' => 0.0,
            'horas_preenchidas' => '0h',
            'horas_uteis' => '0h',
            'rascunhos_total' => 0,
            'sms_falhados_total' => 0,
            'lojas_caixa_aberta' => 0,
            'lojas_total' => 0,
            'top_servicos' => collect(),
            'top_categorias' => collect(),
            'comissoes_tecnicos' => collect(),
            'evolucao' => [
                'mode' => $evolucaoMode,
                'labels' => [],
                'keys' => [],
                'series' => [],
            ],
            'por_loja' => collect(),
        ];
    }

    /**
     * @param  array{start: Carbon, end: Carbon}  $range
     * @return array{caixa_aberta: bool, caixa_label: string, rascunhos: int, sms_falhados: int}
     */
    private function operationalStatus(Store $store, array $range): array
    {
        $storeId = (int) $store->id;
        $open = $this->cashRegisterService->getOpenSession($storeId);
        $caixaAberta = $open !== null;

        $today = StoreBusinessTime::nowForStore($storeId);
        $dayRange = $this->utcRangeForStore($storeId, $today->toDateString(), $today->toDateString());
        $closedToday = CashRegisterSession::query()
            ->forStore($storeId)
            ->where('status', CashRegisterSession::STATUS_CLOSED)
            ->whereBetween('closed_at', [$dayRange['start'], $dayRange['end']])
            ->exists();

        if ($caixaAberta) {
            $caixaLabel = 'Aberta';
        } elseif ($closedToday) {
            $caixaLabel = 'Fechada hoje';
        } else {
            $caixaLabel = 'Sem sessão hoje';
        }

        $rascunhos = (int) Sale::query()
            ->where('store_id', $storeId)
            ->where('invoice_status', Sale::INVOICE_STATUS_RASCUNHO)
            ->where('status', '!=', Sale::STATUS_ANULADO)
            ->count();

        $smsTwilio = (int) SmsMessage::query()
            ->where('store_id', $storeId)
            ->whereIn('twilio_status', ['failed', 'undelivered'])
            ->where(function ($q) use ($range) {
                $q->whereBetween('sent_at', [$range['start'], $range['end']])
                    ->orWhere(function ($q2) use ($range) {
                        $q2->whereNull('sent_at')
                            ->whereBetween('created_at', [$range['start'], $range['end']]);
                    });
            })
            ->count();

        $smsReminders = (int) CalendarEvent::query()
            ->where('store_id', $storeId)
            ->whereNotNull('booking_sms_reminder_failed_at')
            ->whereBetween('booking_sms_reminder_failed_at', [$range['start'], $range['end']])
            ->count();

        return [
            'caixa_aberta' => $caixaAberta,
            'caixa_label' => $caixaLabel,
            'rascunhos' => $rascunhos,
            'sms_falhados' => $smsTwilio + $smsReminders,
        ];
    }

    /**
     * @param  array{start: Carbon, end: Carbon}  $range
     * @return Collection<int, object{nome: string, receita: float, qtd: int}>
     */
    private function topServicosForStore(int $storeId, array $range, int $limit): Collection
    {
        return DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('services', 'sale_items.service_id', '=', 'services.id')
            ->join('calendar_events', 'sales.calendar_event_id', '=', 'calendar_events.id')
            ->where('sales.store_id', $storeId)
            ->where('calendar_events.store_id', $storeId)
            ->where('sales.status', Sale::STATUS_PAGO)
            ->where('sale_items.tipo', SaleItem::TIPO_SERVICO)
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('calendar_events.status', CalendarEvent::STATUS_COMPLETO)
            ->whereBetween('calendar_events.start_at', [$range['start'], $range['end']])
            ->groupBy('services.name')
            ->selectRaw('services.name as nome, sum(sale_items.subtotal) as receita, count(*) as qtd')
            ->orderByDesc('receita')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (object) [
                'nome' => (string) $row->nome,
                'receita' => round((float) $row->receita, 2),
                'qtd' => (int) $row->qtd,
            ]);
    }

    /**
     * @param  array{start: Carbon, end: Carbon}  $range
     * @return Collection<int, object{nome: string, receita: float, qtd: int}>
     */
    private function topCategoriasForStore(int $storeId, array $range, int $limit): Collection
    {
        return DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('services', 'sale_items.service_id', '=', 'services.id')
            ->join('categories', 'services.category_id', '=', 'categories.id')
            ->join('calendar_events', 'sales.calendar_event_id', '=', 'calendar_events.id')
            ->where('sales.store_id', $storeId)
            ->where('calendar_events.store_id', $storeId)
            ->where('sales.status', Sale::STATUS_PAGO)
            ->where('sale_items.tipo', SaleItem::TIPO_SERVICO)
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('calendar_events.status', CalendarEvent::STATUS_COMPLETO)
            ->whereBetween('calendar_events.start_at', [$range['start'], $range['end']])
            ->groupBy('categories.name')
            ->selectRaw('categories.name as nome, sum(sale_items.subtotal) as receita, count(*) as qtd')
            ->orderByDesc('receita')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (object) [
                'nome' => (string) $row->nome,
                'receita' => round((float) $row->receita, 2),
                'qtd' => (int) $row->qtd,
            ]);
    }

    /**
     * @return Collection<int, object{user_id: int, nome: string, comissao: float}>
     */
    private function comissoesPorTecnicoForStore(Store $store, string $desde, string $ate): Collection
    {
        $byUser = $this->withStoreContext($store, function () use ($desde, $ate): array {
            return $this->comissoesReportService->comissaoPorUserId([
                'desde' => $desde,
                'ate' => $ate,
            ]);
        });

        if ($byUser === []) {
            return collect();
        }

        $names = User::query()
            ->whereIn('id', array_keys($byUser))
            ->pluck('name', 'id');

        return collect($byUser)
            ->map(fn (float $comissao, int $userId) => (object) [
                'user_id' => $userId,
                'nome' => (string) ($names[$userId] ?? 'Utilizador #'.$userId),
                'comissao' => round($comissao, 2),
            ])
            ->filter(fn (object $row) => $row->comissao > 0)
            ->sortByDesc('comissao')
            ->values();
    }

    /**
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    private function withStoreContext(Store $store, callable $callback): mixed
    {
        $current = app(CurrentStore::class);
        $previous = $current->tryGet();
        $current->set($store);
        try {
            return $callback();
        } finally {
            if ($previous instanceof Store) {
                $current->set($previous);
            }
        }
    }

    /**
     * @param  array{start: Carbon, end: Carbon}  $range
     */
    private function salesPaidInRange(int $storeId, array $range): object
    {
        return Sale::query()
            ->where('store_id', $storeId)
            ->where('status', Sale::STATUS_PAGO)
            ->whereHas('calendarEvent', function ($q) use ($storeId, $range) {
                $q->where('store_id', $storeId)
                    ->where('event_type', CalendarEvent::TYPE_MARCACAO)
                    ->where('status', CalendarEvent::STATUS_COMPLETO)
                    ->whereBetween('start_at', [$range['start'], $range['end']]);
            })
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total), 0) as receita')
            ->first() ?? (object) ['cnt' => 0, 'receita' => 0];
    }

    /**
     * @param  array{start: Carbon, end: Carbon}  $range
     * @return array{previsto: float, por_fazer: float}
     */
    private function pipelineInRange(int $storeId, array $range): array
    {
        $events = CalendarEvent::query()
            ->where('store_id', $storeId)
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->whereNotIn('status', [
                CalendarEvent::STATUS_CANCELADO,
                CalendarEvent::STATUS_ANULADO,
                CalendarEvent::STATUS_FALTOU,
            ])
            ->whereBetween('start_at', [$range['start'], $range['end']])
            ->with(['eventServiceItems.extras.extra'])
            ->get();

        return MarcacaoMoneyBatch::sumPipelineTotals($events);
    }

    /**
     * @param  array{start: Carbon, end: Carbon}  $range
     */
    private function marcacoesCounts(int $storeId, array $range): object
    {
        return CalendarEvent::query()
            ->where('store_id', $storeId)
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->whereBetween('start_at', [$range['start'], $range['end']])
            ->selectRaw(
                'COUNT(*) as total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completas,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as faltou,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as cancelado,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as anulado',
                [
                    CalendarEvent::STATUS_COMPLETO,
                    CalendarEvent::STATUS_FALTOU,
                    CalendarEvent::STATUS_CANCELADO,
                    CalendarEvent::STATUS_ANULADO,
                ]
            )
            ->first() ?? (object) [
                'total' => 0,
                'completas' => 0,
                'faltou' => 0,
                'cancelado' => 0,
                'anulado' => 0,
            ];
    }

    /**
     * Receita paga por dia civil da loja (chave Y-m-d no fuso da loja).
     *
     * @param  array{start: Carbon, end: Carbon}  $range
     * @return array<string, float>
     */
    private function dailyRevenueMap(int $storeId, array $range, string $tz): array
    {
        $sales = Sale::query()
            ->where('store_id', $storeId)
            ->where('status', Sale::STATUS_PAGO)
            ->whereHas('calendarEvent', function ($q) use ($storeId, $range) {
                $q->where('store_id', $storeId)
                    ->where('event_type', CalendarEvent::TYPE_MARCACAO)
                    ->where('status', CalendarEvent::STATUS_COMPLETO)
                    ->whereBetween('start_at', [$range['start'], $range['end']]);
            })
            ->with(['calendarEvent:id,start_at'])
            ->get(['id', 'total', 'calendar_event_id']);

        $byDay = [];
        foreach ($sales as $sale) {
            $startAt = $sale->calendarEvent?->start_at;
            if ($startAt === null) {
                continue;
            }
            $day = $startAt->copy()->timezone($tz)->toDateString();
            $byDay[$day] = ($byDay[$day] ?? 0.0) + (float) $sale->total;
        }

        foreach ($byDay as $day => $value) {
            $byDay[$day] = round($value, 2);
        }

        return $byDay;
    }

    /**
     * @param  list<array{store_id: int, nome: string, by_day: array<string, float>}>  $porLoja
     * @return array{mode: string, labels: list<string>, keys: list<string>, series: list<array{name: string, data: list<float>}>}
     */
    private function buildEvolutionSeries(string $desde, string $ate, array $porLoja, string $mode): array
    {
        $buckets = $mode === 'semanal'
            ? $this->weeklyBuckets($desde, $ate)
            : $this->dailyBuckets($desde, $ate);

        $keys = array_column($buckets, 'key');
        $labels = array_column($buckets, 'label');
        $orgData = array_fill(0, count($keys), 0.0);
        $series = [];

        foreach ($porLoja as $loja) {
            $data = [];
            foreach ($buckets as $i => $bucket) {
                $sum = 0.0;
                foreach ($loja['by_day'] as $day => $receita) {
                    $bucketKey = $mode === 'semanal'
                        ? Carbon::parse($day)->startOfWeek(Carbon::MONDAY)->toDateString()
                        : $day;
                    if ($bucketKey === $bucket['key']) {
                        $sum += $receita;
                    }
                }
                $sum = round($sum, 2);
                $data[] = $sum;
                $orgData[$i] = round($orgData[$i] + $sum, 2);
            }
            $series[] = [
                'name' => $loja['nome'],
                'data' => $data,
            ];
        }

        array_unshift($series, [
            'name' => 'Empresa',
            'data' => $orgData,
        ]);

        return [
            'mode' => $mode,
            'labels' => $labels,
            'keys' => $keys,
            'series' => $series,
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function dailyBuckets(string $desde, string $ate): array
    {
        $buckets = [];
        $cursor = Carbon::parse($desde)->startOfDay();
        $end = Carbon::parse($ate)->startOfDay();
        while ($cursor->lte($end)) {
            $buckets[] = [
                'key' => $cursor->toDateString(),
                'label' => $cursor->format('d/m'),
            ];
            $cursor->addDay();
        }

        return $buckets;
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function weeklyBuckets(string $desde, string $ate): array
    {
        $buckets = [];
        $cursor = Carbon::parse($desde)->startOfWeek(Carbon::MONDAY);
        $end = Carbon::parse($ate)->startOfDay();
        while ($cursor->lte($end)) {
            $weekEnd = $cursor->copy()->endOfWeek(Carbon::SUNDAY);
            $buckets[] = [
                'key' => $cursor->toDateString(),
                'label' => $cursor->format('d/m').'–'.$weekEnd->format('d/m'),
            ];
            $cursor->addWeek();
        }

        return $buckets;
    }

    /**
     * @param  array{start: Carbon, end: Carbon}  $range
     * @return list<int>
     */
    private function uniqueClientIdsAttended(int $storeId, array $range): array
    {
        $nowUtc = StoreBusinessTime::nowUtcForStore($storeId);

        return CalendarEvent::query()
            ->where('store_id', $storeId)
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->whereNotNull('client_id')
            ->whereBetween('start_at', [$range['start'], $range['end']])
            ->alreadyPassed($nowUtc)
            ->distinct()
            ->pluck('client_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();
    }

    private function deltaPct(float $current, float $previous): ?float
    {
        if (abs($previous) < 0.00001) {
            return abs($current) < 0.00001 ? null : 100.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    private function utcRangeForStore(int $storeId, string $desde, string $ate): array
    {
        $tz = StoreBusinessTime::timezoneForStore($storeId);
        $start = Carbon::parse($desde, $tz)->startOfDay()->utc();
        $end = Carbon::parse($ate, $tz)->endOfDay()->utc();

        return ['start' => $start, 'end' => $end];
    }
}
