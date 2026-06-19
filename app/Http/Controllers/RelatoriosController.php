<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\User;
use App\Support\ApplicableFees;
use App\Support\DateTimeDisplay;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RelatoriosController extends Controller
{
    public function marcacoes(Request $request): View
    {
        $marcacoes = $this->marcacoesReportQuery($request)
            ->with(['user', 'client', 'eventServiceItems.service.category', 'eventServiceItems.extras.extra'])
            ->orderByDesc('start_at')
            ->paginate(100)
            ->withQueryString();

        $servicosOpts = Service::query()
            ->forStore(current_store_id())
            ->join('calendar_event_services', 'services.id', '=', 'calendar_event_services.service_id')
            ->join('calendar_events', 'calendar_events.id', '=', 'calendar_event_services.calendar_event_id')
            ->where('calendar_events.store_id', current_store_id())
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->select('services.id', 'services.name')
            ->distinct()
            ->orderBy('services.name')
            ->get();

        $tecnicosOpts = $this->membrosOptsForRelatorios();

        $clientesOpts = Client::query()
            ->forStore(current_store_id())
            ->join('calendar_events', 'calendar_events.client_id', '=', 'clients.id')
            ->where('calendar_events.store_id', current_store_id())
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->select('clients.id', 'clients.name')
            ->distinct()
            ->orderBy('clients.name')
            ->get();

        $today = now()->startOfDay();
        $firstDayOfMonth = now()->copy()->startOfMonth();
        $marcacoesDesde = $request->get('marcacoes_desde') ?: $firstDayOfMonth->toDateString();
        $marcacoesAte = $request->get('marcacoes_ate') ?: $today->toDateString();

        return view('relatorios.marcacoes', [
            'pageTitle' => 'Relatórios — Marcações',
            'marcacoes' => $marcacoes,
            'marcacoesDesde' => $marcacoesDesde,
            'marcacoesAte' => $marcacoesAte,
            'marcacoesServico' => $request->get('marcacoes_servico'),
            'marcacoesTecnico' => $request->get('marcacoes_tecnico'),
            'marcacoesEstado' => $request->get('marcacoes_estado'),
            'marcacoesCliente' => $request->get('marcacoes_cliente'),
            'servicosOpts' => $servicosOpts,
            'tecnicosOpts' => $tecnicosOpts,
            'clientesOpts' => $clientesOpts,
            'marcacoesTotais' => $this->marcacoesReportTotals($request),
        ]);
    }

    public function marcacoesExport(Request $request): StreamedResponse
    {
        $events = $this->marcacoesReportQuery($request)
            ->with(['user', 'client', 'eventServiceItems.service.category', 'eventServiceItems.extras.extra'])
            ->orderByDesc('start_at')
            ->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Marcações');

        $headers = [
            'Data/Hora início',
            'Estado',
            'Cliente',
            'Técnico',
            'Serviços',
            'Categoria',
            'Preço total (€)',
            'Notas',
        ];
        $sheet->fromArray($headers, null, 'A1');

        $rowIndex = 2;
        foreach ($events as $ev) {
            $totalPreco = $ev->eventServiceItems->sum(function ($es) {
                return (float) $es->price + $es->extras->sum(fn ($x) => (float) $x->price);
            });
            $services = $ev->eventServiceItems
                ->map(function ($es) {
                    $optionName = trim((string) ($es->option_name ?? ''));

                    return $optionName !== '' ? $optionName : ($es->service?->name ?? null);
                })
                ->filter()
                ->implode(', ');
            $categorias = $ev->eventServiceItems
                ->map(fn ($es) => $es->service?->category?->name)
                ->map(fn ($n) => $n !== null && $n !== '' ? $n : '—')
                ->implode(', ');
            $statusLabel = CalendarEvent::statuses()[$ev->status] ?? $ev->status;

            $sheet->fromArray([
                [
                    DateTimeDisplay::business($ev->start_at),
                    $statusLabel,
                    $ev->client?->name ?? '',
                    $ev->user?->name ?? '',
                    $services,
                    $categorias,
                    round($totalPreco, 2),
                    $ev->description ?? '',
                ],
            ], null, 'A'.$rowIndex);
            $rowIndex++;
        }

        $totais = $this->marcacoesTotaisFromEvents($events);
        $sheet->fromArray([
            [
                '',
                '',
                '',
                '',
                'Total',
                $totais['servicos_count'].' serviço(s)',
                round($totais['preco_total'], 2),
                '',
            ],
        ], null, 'A'.$rowIndex);

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'marcacoes_'.now()->format('Y-m-d_His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * PDF do relatório de marcações (mesmos filtros que a listagem; todos os registos filtrados).
     */
    public function marcacoesPdf(Request $request)
    {
        $events = $this->marcacoesReportQuery($request)
            ->with(['user', 'client', 'eventServiceItems.service.category', 'eventServiceItems.extras'])
            ->orderByDesc('start_at')
            ->get();

        $pdf = Pdf::loadView('relatorios.pdf.marcacoes', [
            'marcacoes' => $events,
            'filtrosLinhas' => $this->marcacoesFiltrosResumo($request),
            'appName' => config('app.name'),
            'totalRegistos' => $events->count(),
            'marcacoesTotais' => $this->marcacoesTotaisFromEvents($events),
        ])->setPaper('a4', 'landscape');

        $filename = 'marcacoes_'.now()->format('Y-m-d_His').'.pdf';

        return $pdf->stream($filename);
    }

    /**
     * Linhas de texto descrevendo os filtros efetivos (para cabeçalho do PDF).
     *
     * @return array<int, string>
     */
    private function marcacoesFiltrosResumo(Request $request): array
    {
        $today = now()->startOfDay();
        $firstDayOfMonth = now()->copy()->startOfMonth();
        $desde = $request->get('marcacoes_desde') ?: $firstDayOfMonth->toDateString();
        $ate = $request->get('marcacoes_ate') ?: $today->toDateString();

        $lines = [
            'Período: '.Carbon::parse($desde)->format('d/m/Y').' a '.Carbon::parse($ate)->format('d/m/Y'),
        ];

        if ($cid = $request->get('marcacoes_cliente')) {
            $lines[] = 'Cliente: '.(Client::query()->forStore(current_store_id())->find($cid)?->name ?? '—');
        }
        if ($sid = $request->get('marcacoes_servico')) {
            $lines[] = 'Serviço: '.(Service::query()->forStore(current_store_id())->find($sid)?->name ?? '—');
        }
        if ($tid = $request->get('marcacoes_tecnico')) {
            $lines[] = 'Técnico: '.(User::activeStaff(current_store_id())->find($tid)?->name ?? '—');
        }
        if ($est = $request->get('marcacoes_estado')) {
            $lines[] = 'Estado: '.(CalendarEvent::statuses()[$est] ?? $est);
        }

        return $lines;
    }

    /**
     * Query base do relatório de marcações (mesmos filtros na listagem e na exportação).
     */
    private function marcacoesReportQuery(Request $request): Builder
    {
        $today = now()->startOfDay();
        $firstDayOfMonth = now()->copy()->startOfMonth();

        $marcacoesDesde = $request->get('marcacoes_desde');
        $marcacoesAte = $request->get('marcacoes_ate');
        $marcacoesServico = $request->get('marcacoes_servico');
        $marcacoesTecnico = $request->get('marcacoes_tecnico');
        $marcacoesEstado = $request->get('marcacoes_estado');
        $marcacoesCliente = $request->get('marcacoes_cliente');

        if (! $marcacoesDesde) {
            $marcacoesDesde = $firstDayOfMonth->toDateString();
        }
        if (! $marcacoesAte) {
            $marcacoesAte = $today->toDateString();
        }

        $marcacoesQuery = CalendarEvent::query()
            ->forStore(current_store_id())
            ->where('event_type', CalendarEvent::TYPE_MARCACAO);

        if ($marcacoesDesde) {
            $marcacoesQuery->whereDate('start_at', '>=', $marcacoesDesde);
        }
        if ($marcacoesAte) {
            $marcacoesQuery->whereDate('start_at', '<=', $marcacoesAte);
        }
        if ($marcacoesServico) {
            $marcacoesQuery->whereHas('eventServiceItems', fn ($q) => $q->where('service_id', $marcacoesServico));
        }
        if ($marcacoesTecnico) {
            $marcacoesQuery->where('user_id', $marcacoesTecnico);
        }
        if ($marcacoesEstado) {
            $marcacoesQuery->where('status', $marcacoesEstado);
        }
        if ($marcacoesCliente) {
            $marcacoesQuery->where('client_id', $marcacoesCliente);
        }

        return $marcacoesQuery;
    }

    /**
     * Soma do preço (serviços + extras) e contagem de linhas de serviço para o relatório de marcações (filtros atuais).
     *
     * @return array{preco_total: float, servicos_count: int}
     */
    private function marcacoesReportTotals(Request $request): array
    {
        $eventIds = $this->marcacoesReportQuery($request)->select('calendar_events.id');

        $serviceSum = (float) DB::table('calendar_event_services')
            ->whereIn('calendar_event_id', $eventIds)
            ->sum('price');

        $extraSum = (float) DB::table('calendar_event_service_extras as cee')
            ->join('calendar_event_services as ces', 'cee.calendar_event_service_id', '=', 'ces.id')
            ->whereIn('ces.calendar_event_id', $eventIds)
            ->sum('cee.price');

        $servicosCount = (int) DB::table('calendar_event_services')
            ->whereIn('calendar_event_id', $eventIds)
            ->count();

        return [
            'preco_total' => $serviceSum + $extraSum,
            'servicos_count' => $servicosCount,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CalendarEvent>  $events
     * @return array{preco_total: float, servicos_count: int}
     */
    private function marcacoesTotaisFromEvents(Collection $events): array
    {
        $preco = 0.0;
        $count = 0;
        foreach ($events as $ev) {
            foreach ($ev->eventServiceItems as $es) {
                $count++;
                $preco += (float) $es->price + $es->extras->sum(fn ($x) => (float) $x->price);
            }
        }

        return [
            'preco_total' => $preco,
            'servicos_count' => $count,
        ];
    }

    public function vendas(Request $request): View
    {
        $sales = $this->vendasReportQuery($request)
            ->with(['client', 'calendarEvent.user', 'calendarEvent.eventServiceItems.extras.extra', 'items.service', 'items.extra', 'items.calendarEventService.service'])
            ->orderByDesc('data_emissao')
            ->orderByDesc('id')
            ->get();

        $allLines = $this->vendasResumoCollection($sales, $request->get('vendas_servico'));

        $page = max(1, (int) $request->get('page', 1));
        $perPage = 100;
        $slice = $allLines->slice(($page - 1) * $perPage, $perPage)->values();

        $vendas = new LengthAwarePaginator(
            $slice,
            $allLines->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'pageName' => 'page',
            ]
        );
        $vendas->withQueryString();

        $today = now()->startOfDay();
        $firstDayOfMonth = now()->copy()->startOfMonth();
        $vendasDesde = $request->get('vendas_desde') ?: $firstDayOfMonth->toDateString();
        $vendasAte = $request->get('vendas_ate') ?: $today->toDateString();

        return view('relatorios.vendas', [
            'pageTitle' => 'Relatórios — Vendas',
            'vendas' => $vendas,
            'vendasDesde' => $vendasDesde,
            'vendasAte' => $vendasAte,
            'vendasCliente' => $request->get('vendas_cliente'),
            'vendasServico' => $request->get('vendas_servico'),
            'vendasTecnico' => $request->get('vendas_tecnico'),
            'vendasEstado' => $request->get('vendas_estado'),
            'clientesOpts' => $this->vendasClientesOpts(),
            'servicosOpts' => $this->vendasServicosOpts(),
            'tecnicosOpts' => $this->membrosOptsForRelatorios(),
            'vendasTotais' => $this->vendasTotaisRodape($allLines),
        ]);
    }

    public function vendasExport(Request $request): StreamedResponse
    {
        $sales = $this->vendasReportQuery($request)
            ->with(['client', 'calendarEvent.user', 'calendarEvent.eventServiceItems.extras.extra', 'items.service', 'items.extra', 'items.calendarEventService.service'])
            ->orderByDesc('data_emissao')
            ->orderByDesc('id')
            ->get();

        $lines = $this->vendasResumoCollection($sales, $request->get('vendas_servico'));

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Vendas');

        $headers = [
            'Data emissão',
            'N.º fatura',
            'Cliente',
            'NIF',
            'Técnico',
            'Serviço',
            'Total (€)',
            'Taxas (€)',
            'Gorjeta (€)',
            'Em dívida (€)',
            'Estado fatura',
        ];
        $sheet->fromArray($headers, null, 'A1');

        $rowIndex = 2;
        foreach ($lines as $linha) {
            $sheet->fromArray([
                [
                    $linha->data->format('d/m/Y'),
                    $linha->numero_fatura,
                    $linha->cliente,
                    $linha->nif,
                    $linha->tecnico,
                    $linha->servico,
                    round((float) $linha->valor + (float) ($linha->gorjeta ?? 0), 2),
                    round((float) ($linha->taxas ?? 0), 2),
                    round((float) ($linha->gorjeta ?? 0), 2),
                    round((float) ($linha->pendente ?? 0), 2),
                    ! empty($linha->is_anulado)
                        ? 'Anulada'
                        : (($linha->invoice_status ?? Sale::INVOICE_STATUS_FATURADO) === Sale::INVOICE_STATUS_RASCUNHO
                            ? 'Rascunho'
                            : 'Faturado'),
                ],
            ], null, 'A'.$rowIndex);
            $rowIndex++;
        }

        $totais = $this->vendasTotaisRodape($lines);
        $sheet->fromArray([
            [
                '',
                '',
                '',
                '',
                '',
                'Subtotal',
                round($totais['total_valor_com_gorjeta'], 2),
                round($totais['total_taxas'] ?? 0, 2),
                round($totais['total_gorjeta'], 2),
                round($totais['total_divida'], 2),
                '',
            ],
        ], null, 'A'.$rowIndex);
        $rowIndex++;
        $sheet->fromArray([
            [
                '',
                '',
                '',
                '',
                '',
                'Total',
                round($totais['total_absoluto'], 2),
                '',
                '',
                '',
                '',
            ],
        ], null, 'A'.$rowIndex);

        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'vendas_'.now()->format('Y-m-d_His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function vendasPdf(Request $request)
    {
        $sales = $this->vendasReportQuery($request)
            ->with(['client', 'calendarEvent.user', 'calendarEvent.eventServiceItems.extras.extra', 'items.service', 'items.extra', 'items.calendarEventService.service'])
            ->orderByDesc('data_emissao')
            ->orderByDesc('id')
            ->get();

        $lines = $this->vendasResumoCollection($sales, $request->get('vendas_servico'));

        $pdf = Pdf::loadView('relatorios.pdf.vendas', [
            'linhas' => $lines,
            'filtrosLinhas' => $this->vendasFiltrosResumo($request),
            'appName' => config('app.name'),
            'totalLinhas' => $lines->count(),
            'vendasTotais' => $this->vendasTotaisRodape($lines),
        ])->setPaper('a4', 'landscape');

        $filename = 'vendas_'.now()->format('Y-m-d_His').'.pdf';

        return $pdf->stream($filename);
    }

    /**
     * @return array<int, string>
     */
    private function vendasFiltrosResumo(Request $request): array
    {
        $today = now()->startOfDay();
        $firstDayOfMonth = now()->copy()->startOfMonth();
        $desde = $request->get('vendas_desde') ?: $firstDayOfMonth->toDateString();
        $ate = $request->get('vendas_ate') ?: $today->toDateString();

        $lines = [
            'Período (emissão): '.Carbon::parse($desde)->format('d/m/Y').' a '.Carbon::parse($ate)->format('d/m/Y'),
        ];

        if ($cid = $request->get('vendas_cliente')) {
            $lines[] = 'Cliente: '.(Client::query()->forStore(current_store_id())->find($cid)?->name ?? '—');
        }
        if ($sid = $request->get('vendas_servico')) {
            $lines[] = 'Serviço: '.(Service::query()->forStore(current_store_id())->find($sid)?->name ?? '—');
        }
        if ($tid = $request->get('vendas_tecnico')) {
            $lines[] = 'Técnico: '.(User::activeStaff(current_store_id())->find($tid)?->name ?? '—');
        }
        if ($est = $request->get('vendas_estado')) {
            $label = $est === Sale::INVOICE_STATUS_RASCUNHO ? 'Rascunho' : ($est === Sale::INVOICE_STATUS_FATURADO ? 'Faturado' : $est);
            $lines[] = 'Estado da fatura: '.$label;
        } else {
            $lines[] = 'Estado da fatura: Faturado e Rascunho';
        }

        return $lines;
    }

    private function vendasReportQuery(Request $request): Builder
    {
        $today = now()->startOfDay();
        $firstDayOfMonth = now()->copy()->startOfMonth();

        $desde = $request->get('vendas_desde');
        $ate = $request->get('vendas_ate');
        $cliente = $request->get('vendas_cliente');
        $servico = $request->get('vendas_servico');
        $tecnico = $request->get('vendas_tecnico');
        $estado = (string) $request->get('vendas_estado', '');

        if (! $desde) {
            $desde = $firstDayOfMonth->toDateString();
        }
        if (! $ate) {
            $ate = $today->toDateString();
        }

        $q = Sale::query()
            ->where('store_id', current_store_id())
            ->whereHas('calendarEvent', function (Builder $cq) {
                $cq->where('store_id', current_store_id())
                    ->where('event_type', CalendarEvent::TYPE_MARCACAO)
                    ->where('status', '!=', CalendarEvent::STATUS_CANCELADO);
            });

        if (in_array($estado, [Sale::INVOICE_STATUS_FATURADO, Sale::INVOICE_STATUS_RASCUNHO], true)) {
            $q->where('invoice_status', $estado);
        } else {
            $q->whereIn('invoice_status', [Sale::INVOICE_STATUS_FATURADO, Sale::INVOICE_STATUS_RASCUNHO]);
        }

        $q->whereDate('data_emissao', '>=', $desde);
        $q->whereDate('data_emissao', '<=', $ate);

        if ($cliente) {
            $q->where('client_id', $cliente);
        }
        if ($servico) {
            $q->whereHas('items', function (Builder $iq) use ($servico) {
                $iq->where('tipo', SaleItem::TIPO_SERVICO)
                    ->where('service_id', $servico);
            });
        }
        if ($tecnico) {
            $q->whereHas('calendarEvent', fn (Builder $cq) => $cq->where('store_id', current_store_id())->where('user_id', $tecnico));
        }

        return $q;
    }

    /**
     * Totais do rodapé do relatório de vendas (todas as linhas do filtro).
     *
     * @param  Collection<int, object>  $lines
     * @return array{total_valor: float, total_valor_com_gorjeta: float, total_gorjeta: float, total_taxas: float, total_absoluto: float, num_vendas: int, total_servicos: int, total_desconto: float, total_divida: float}
     */
    private function vendasTotaisRodape(Collection $lines): array
    {
        $totalValor = 0.0;
        $totalDesconto = 0.0;
        $totalGorjeta = 0.0;
        $totalTaxas = 0.0;
        $totalServicos = 0;
        // Vendas anuladas (com nota de crédito) não contam para os totais — anulam-se com a NC.
        $activeLines = $lines->filter(fn ($linha): bool => empty($linha->is_anulado));
        foreach ($activeLines as $linha) {
            $totalValor += (float) $linha->valor;
            $totalDesconto += (float) ($linha->desconto ?? 0);
            $totalGorjeta += (float) ($linha->gorjeta ?? 0);
            $totalTaxas += (float) ($linha->taxas ?? 0);
            $isServiceLine = ($linha->tipo_item ?? null) === SaleItem::TIPO_SERVICO
                || ($linha->tipo_item ?? null) === 'resumo';
            if ($isServiceLine) {
                $totalServicos += (int) ($linha->quantidade ?? 0);
            }
        }

        $numVendas = $activeLines->pluck('sale_id')->unique()->count();

        $totalDivida = 0.0;
        $vistoSale = [];
        foreach ($activeLines as $linha) {
            $sid = (int) $linha->sale_id;
            if (isset($vistoSale[$sid])) {
                continue;
            }
            $vistoSale[$sid] = true;
            $totalDivida += (float) ($linha->pendente ?? 0);
        }

        $totalValorComGorjeta = round($totalValor + $totalGorjeta, 2);
        $totalTaxasRounded = round($totalTaxas, 2);

        return [
            'total_valor' => round($totalValor, 2),
            'total_valor_com_gorjeta' => $totalValorComGorjeta,
            'total_gorjeta' => round($totalGorjeta, 2),
            'total_taxas' => $totalTaxasRounded,
            'total_absoluto' => round($totalValorComGorjeta + $totalTaxasRounded, 2),
            'num_vendas' => $numVendas,
            'total_servicos' => $totalServicos,
            'total_desconto' => round($totalDesconto, 2),
            'total_divida' => round($totalDivida, 2),
        ];
    }

    private function vendasPendenteForSale(Sale $sale): float
    {
        if ($sale->status === Sale::STATUS_ANULADO) {
            return 0.0;
        }

        if ($sale->scope === Sale::SCOPE_BOOKING_RESERVA) {
            $eventId = (int) ($sale->calendar_event_id ?? 0);
            if ($eventId <= 0 || $this->vendasMarcacaoHasActiveCaixaSale($eventId)) {
                return 0.0;
            }

            $event = $sale->calendarEvent;
            if (! $event) {
                return 0.0;
            }

            if (! $event->relationLoaded('eventServiceItems')) {
                $event->load(['eventServiceItems.extras.extra']);
            }

            $chargeSubtotal = ApplicableFees::chargeSubtotalForCalendarEvent($event, $event->eventServiceItems);

            return ApplicableFees::amountDueCashFromEventId($eventId, $chargeSubtotal);
        }

        return $sale->amountDue();
    }

    private function vendasMarcacaoHasActiveCaixaSale(int $calendarEventId): bool
    {
        if ($calendarEventId <= 0) {
            return false;
        }

        $saleIds = Sale::query()
            ->where('calendar_event_id', $calendarEventId)
            ->pluck('id')
            ->merge(
                DB::table('sale_calendar_events')
                    ->where('calendar_event_id', $calendarEventId)
                    ->pluck('sale_id')
            )
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($saleIds === []) {
            return false;
        }

        return Sale::query()
            ->whereIn('id', $saleIds)
            ->where('status', '!=', Sale::STATUS_ANULADO)
            ->where('scope', Sale::SCOPE_CAIXA_LIQUIDACAO)
            ->exists();
    }

    /**
     * Uma linha por venda, agregando os serviços executados (sem extras).
     *
     * @return Collection<int, object>
     */
    private function vendasResumoCollection(Collection $sales, ?string $vendasServico): Collection
    {
        $servicoFilter = $vendasServico !== null && $vendasServico !== '' ? (int) $vendasServico : null;

        return $sales->map(function (Sale $sale) use ($servicoFilter) {
            $serviceItems = $sale->items
                ->where('tipo', SaleItem::TIPO_SERVICO)
                ->when($servicoFilter, fn ($c) => $c->where('service_id', $servicoFilter))
                ->values();
            if ($serviceItems->isEmpty()) {
                return null;
            }

            $serviceLabels = $serviceItems
                ->map(fn (SaleItem $item) => $this->serviceLabelForSaleItem($item))
                ->filter(fn (?string $label) => $label !== null && trim($label) !== '')
                ->values();

            $event = $sale->calendarEvent;
            $client = $sale->client;

            return (object) [
                'sale' => $sale,
                'sale_id' => $sale->id,
                'sale_status' => $sale->status,
                'is_anulado' => $sale->isAnulado(),
                'credit_note_pdf_url' => $sale->hasCreditNote() ? route('sales.credit-note.pdf', $sale) : null,
                'invoice_status' => $sale->invoice_status ?? Sale::INVOICE_STATUS_FATURADO,
                'data' => $sale->data_emissao,
                'numero_fatura' => $sale->numero_fatura,
                'cliente' => $client?->name ?? '—',
                'nif' => $client?->nif ?? '',
                'tecnico' => $event?->user?->name ?? '—',
                ...$this->vendasServicoColunaForSale($sale, $serviceLabels),
                'quantidade' => (int) $serviceItems->count(),
                'valor' => $this->vendasValorLinhaForSale($sale, $serviceItems),
                'taxas' => (float) $sale->items
                    ->where('tipo', SaleItem::TIPO_TAXA)
                    ->sum(fn (SaleItem $item) => (float) $item->subtotal),
                'tipo_item' => 'resumo',
                'desconto' => (float) ($sale->desconto ?? 0),
                'gorjeta' => (float) ($sale->gorjeta ?? 0),
                'pendente' => $this->vendasPendenteForSale($sale),
                'calendar_event_id' => $sale->calendar_event_id,
            ];
        })->filter()->values();
    }

    /**
     * Valor da coluna «Total» (sem gorjeta): na liquidação em caixa usa o total da fatura
     * (o que faltava pagar), não a soma dos preços de catálogo nas linhas.
     *
     * @param  Collection<int, SaleItem>  $serviceItems
     */
    private function vendasValorLinhaForSale(Sale $sale, Collection $serviceItems): float
    {
        if ($sale->scope === Sale::SCOPE_CAIXA_LIQUIDACAO) {
            $gorjeta = (float) ($sale->gorjeta ?? 0);
            $taxas = (float) $sale->items
                ->where('tipo', SaleItem::TIPO_TAXA)
                ->sum(fn (SaleItem $item) => (float) $item->subtotal);

            return max(0, round((float) $sale->total - $gorjeta - $taxas, 2));
        }

        return (float) $serviceItems->sum(fn (SaleItem $item) => (float) $item->subtotal);
    }

    private function serviceLabelForSaleItem(SaleItem $item): string
    {
        $optionName = trim((string) ($item->calendarEventService?->option_name ?? ''));
        if ($optionName !== '') {
            return $optionName;
        }

        $descricao = trim((string) $item->descricao);
        if ($descricao !== '') {
            return $descricao;
        }

        if ($item->service) {
            return (string) $item->service->name;
        }

        return (string) ($item->calendarEventService?->service?->name ?? '—');
    }

    /**
     * Coluna «Serviço» no relatório de vendas (nomes + subtítulo em linha separada).
     *
     * @param  Collection<int, string>  $serviceLabels
     * @return array{servico: string, servico_nomes: string, servico_subtitulo: ?string}
     */
    private function vendasServicoColunaForSale(Sale $sale, Collection $serviceLabels): array
    {
        $nomesFromEvent = $this->vendasServicoNomesFromEvent($sale);

        if ($sale->scope === Sale::SCOPE_BOOKING_RESERVA) {
            $nomes = $nomesFromEvent ?? $this->vendasPrepagamentoNomesFromDescricao($serviceLabels->first() ?? '');

            return $this->vendasServicoColunaPayload($nomes, 'Pré-pagamento');
        }

        if ($sale->scope === Sale::SCOPE_CAIXA_LIQUIDACAO) {
            $nomes = $nomesFromEvent ?? $this->vendasServicoNomesFromLabels($serviceLabels);

            return $this->vendasServicoColunaPayload($nomes, 'Pagamento em loja');
        }

        $nomes = $this->vendasServicoNomesFromLabels($serviceLabels);

        return $this->vendasServicoColunaPayload($nomes, null);
    }

    /**
     * @return array{servico: string, servico_nomes: string, servico_subtitulo: ?string}
     */
    private function vendasServicoColunaPayload(string $nomes, ?string $subtitulo): array
    {
        $nomes = trim($nomes);
        $subtitulo = $subtitulo !== null && trim($subtitulo) !== '' ? trim($subtitulo) : null;
        $servico = $subtitulo !== null && $nomes !== ''
            ? $nomes."\n".$subtitulo
            : ($nomes !== '' ? $nomes : ($subtitulo ?? '—'));

        return [
            'servico' => $servico,
            'servico_nomes' => $nomes !== '' ? $nomes : '—',
            'servico_subtitulo' => $subtitulo,
        ];
    }

    private function vendasServicoNomesFromEvent(Sale $sale): ?string
    {
        $event = $sale->calendarEvent;
        if (! $event) {
            return null;
        }

        $names = $event->eventServiceItems
            ->map(function ($es) {
                $optionName = trim((string) ($es->option_name ?? ''));

                return $optionName !== '' ? $optionName : ($es->service?->name ?? null);
            })
            ->filter()
            ->values();

        if ($names->isEmpty()) {
            return null;
        }

        return $names->implode(' / ');
    }

    /**
     * @param  Collection<int, string>  $serviceLabels
     */
    private function vendasServicoNomesFromLabels(Collection $serviceLabels): string
    {
        return $serviceLabels
            ->map(fn (string $label): string => preg_replace('/^\s*\d{1,2}:\d{2}\s*-\s*/u', '', $label) ?: $label)
            ->filter(fn (string $label) => trim($label) !== '')
            ->implode(', ');
    }

    private function vendasPrepagamentoNomesFromDescricao(string $descricao): string
    {
        $label = trim($descricao);
        if ($label === '') {
            return '';
        }

        $label = preg_replace('/\s*[—–-]\s*pr[eé]-pagamento\s*(\([^)]*\))?\s*$/iu', '', $label) ?? $label;
        $label = trim($label);

        if (preg_match('/^(.+?)\s+-\s+(.+)$/u', $label, $m)) {
            return trim($m[2]);
        }

        return $label;
    }

    private function vendasClientesOpts(): Collection
    {
        return Client::query()
            ->forStore(current_store_id())
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('sales')
                    ->join('calendar_events', 'calendar_events.id', '=', 'sales.calendar_event_id')
                    ->whereColumn('sales.client_id', 'clients.id')
                    ->where('sales.store_id', current_store_id())
                    ->where('calendar_events.store_id', current_store_id())
                    ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
                    ->where('calendar_events.status', '!=', CalendarEvent::STATUS_CANCELADO);
            })
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function vendasServicosOpts(): Collection
    {
        return Service::query()
            ->forStore(current_store_id())
            ->join('sale_items', 'services.id', '=', 'sale_items.service_id')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('calendar_events', 'sales.calendar_event_id', '=', 'calendar_events.id')
            ->where('sales.store_id', current_store_id())
            ->where('calendar_events.store_id', current_store_id())
            ->where('sale_items.tipo', SaleItem::TIPO_SERVICO)
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('calendar_events.status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->select('services.id', 'services.name')
            ->distinct()
            ->orderBy('services.name')
            ->get();
    }

    /**
     * Membros de equipa activos (filtros dos relatórios).
     *
     * @return Collection<int, User>
     */
    private function membrosOptsForRelatorios(): Collection
    {
        return User::activeStaff(current_store_id())
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function comissoes(): View
    {
        return view('relatorios.comissoes', [
            'pageTitle' => 'Relatórios — Comissões',
        ]);
    }
}
