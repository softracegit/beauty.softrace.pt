<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\BookingFunnelReportService;
use App\Services\ComissoesReportService;
use App\Services\SmsReportService;
use App\Services\VendasReportRunService;
use App\Services\VendasReportService;
use App\Support\ComissoesReportPdfColumns;
use App\Support\DateTimeDisplay;
use App\Support\MarcacoesReportPdfColumns;
use App\Support\TechnicianFilterUserId;
use App\Support\MarcacoesReportEstadoFilter;
use App\Support\StoreBusinessTime;
use App\Support\VendasReportPdfColumns;
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
    public function __construct(
        private readonly VendasReportService $vendasReportService,
        private readonly VendasReportRunService $vendasReportRunService,
        private readonly ComissoesReportService $comissoesReportService,
        private readonly SmsReportService $smsReportService,
        private readonly BookingFunnelReportService $bookingFunnelReportService,
    ) {}

    public function marcacoes(Request $request): View
    {
        $marcacoes = $this->marcacoesReportQuery($request)
            ->with(['user', 'client', 'personalTimeType', 'eventServiceItems.service.category', 'eventServiceItems.extras.extra'])
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

        $marcacoesDesde = $request->get('marcacoes_desde') ?: $this->marcacoesDefaultDesde();
        $marcacoesAte = $request->get('marcacoes_ate') ?: $this->marcacoesDefaultAte();

        return view('relatorios.marcacoes', [
            'pageTitle' => 'Relatórios — Marcações',
            'marcacoes' => $marcacoes,
            'marcacoesDesde' => $marcacoesDesde,
            'marcacoesAte' => $marcacoesAte,
            'marcacoesServico' => $request->get('marcacoes_servico'),
            'marcacoesTecnico' => $request->get('marcacoes_tecnico'),
            'marcacoesEstado' => MarcacoesReportEstadoFilter::resolve($request->get('marcacoes_estado')),
            'marcacoesCliente' => $request->get('marcacoes_cliente'),
            'servicosOpts' => $servicosOpts,
            'tecnicosOpts' => $tecnicosOpts,
            'clientesOpts' => $clientesOpts,
            'marcacoesTotais' => $this->marcacoesReportTotals($request),
            'marcacoesPdfColumnOptions' => MarcacoesReportPdfColumns::labels(),
        ]);
    }

    public function marcacoesExport(Request $request): StreamedResponse
    {
        $events = $this->marcacoesReportQuery($request)
            ->with(['user', 'client', 'personalTimeType', 'eventServiceItems.service.category', 'eventServiceItems.extras.extra'])
            ->orderByDesc('start_at')
            ->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Marcações');

        $headers = [
            'Data',
            'Estado',
            'Cliente',
            'Técnico',
            'Serviço',
            'Origem',
            'Preço total (€)',
            'Notas',
        ];
        $sheet->fromArray($headers, null, 'A1');

        $rowIndex = 2;
        foreach ($events as $ev) {
            $totalPreco = $ev->eventServiceItems->sum(function ($es) {
                return (float) $es->price + $es->extras->sum(fn ($x) => (float) $x->price);
            });

            $sheet->fromArray([
                [
                    MarcacoesReportEstadoFilter::eventRowDataExportCell($ev),
                    MarcacoesReportEstadoFilter::eventRowStatusLabel($ev),
                    $ev->client?->name ?? '',
                    $ev->user?->name ?? '',
                    MarcacoesReportEstadoFilter::eventRowServicoExportCell($ev),
                    MarcacoesReportEstadoFilter::eventRowOrigemLabel($ev),
                    round($totalPreco, 2),
                    $ev->description ?? '',
                ],
            ], null, 'A'.$rowIndex);
            $rowIndex++;
        }

        $totais = MarcacoesReportEstadoFilter::totaisFromEvents($events);
        $sheet->fromArray([
            [
                '',
                '',
                '',
                '',
                '',
                'Total',
                round($totais['preco_total'], 2),
                $totais['servicos_count'].' serviço(s)',
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
            ->with(['user', 'client', 'personalTimeType', 'eventServiceItems.service.category', 'eventServiceItems.extras'])
            ->orderByDesc('start_at')
            ->get();

        $pdfColumns = MarcacoesReportPdfColumns::resolveFromRequest($request);

        $pdf = Pdf::loadView('relatorios.pdf.marcacoes', [
            'marcacoes' => $events,
            'filtrosLinhas' => $this->marcacoesFiltrosResumo($request),
            'appName' => config('app.name'),
            'totalRegistos' => $events->count(),
            'marcacoesTotais' => MarcacoesReportEstadoFilter::totaisFromEvents($events),
            'pdfColumns' => $pdfColumns,
            'pdfColumnLabels' => MarcacoesReportPdfColumns::labels(),
        ])->setPaper('a4', MarcacoesReportPdfColumns::resolveOrientationFromRequest($request));

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
        $desde = $request->get('marcacoes_desde') ?: $this->marcacoesDefaultDesde();
        $ate = $request->get('marcacoes_ate') ?: $this->marcacoesDefaultAte();

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
        $est = MarcacoesReportEstadoFilter::resolve($request->get('marcacoes_estado'));
        $lines[] = 'Estado: '.MarcacoesReportEstadoFilter::label($est);

        return $lines;
    }

    /**
     * Query base do relatório de marcações (mesmos filtros na listagem e na exportação).
     */
    private function marcacoesReportQuery(Request $request): Builder
    {
        $marcacoesDesde = $request->get('marcacoes_desde') ?: $this->marcacoesDefaultDesde();
        $marcacoesAte = $request->get('marcacoes_ate') ?: $this->marcacoesDefaultAte();
        $marcacoesServico = $request->get('marcacoes_servico');
        $marcacoesTecnico = $request->get('marcacoes_tecnico');
        $marcacoesEstado = MarcacoesReportEstadoFilter::resolve($request->get('marcacoes_estado'));
        $marcacoesCliente = $request->get('marcacoes_cliente');

        $marcacoesQuery = MarcacoesReportEstadoFilter::apply(
            CalendarEvent::query()->forStore(current_store_id()),
            $marcacoesEstado,
        );

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

    public function vendas(Request $request): View
    {
        $dateCriterion = $this->vendasDateCriterion($request);
        $sales = $this->vendasSalesForReport($request);

        $allLines = $this->vendasResumoCollection(
            $sales,
            $request->get('vendas_servico'),
            $request->get('vendas_tecnico'),
            $dateCriterion,
        );

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

        $vendasDesde = $request->get('vendas_desde') ?: $this->vendasDefaultDesde();
        $vendasAte = $request->get('vendas_ate') ?: $this->vendasDefaultAte();

        return view('relatorios.vendas', [
            'pageTitle' => 'Relatórios — Vendas',
            'vendas' => $vendas,
            'vendasDesde' => $vendasDesde,
            'vendasAte' => $vendasAte,
            'vendasCliente' => $request->get('vendas_cliente'),
            'vendasServico' => $request->get('vendas_servico'),
            'vendasTecnico' => $request->get('vendas_tecnico'),
            'vendasEstado' => $request->get('vendas_estado'),
            'vendasDataCriterio' => $dateCriterion,
            'vendasDataColunaLabel' => $this->vendasDataColunaLabel($dateCriterion),
            'clientesOpts' => $this->vendasClientesOpts(),
            'servicosOpts' => $this->vendasServicosOpts(),
            'tecnicosOpts' => $this->membrosOptsForRelatorios(),
            'vendasTotais' => $this->vendasTotaisRodape($allLines, $dateCriterion, $sales),
            'vendasPdfColumnOptions' => $this->vendasPdfColumnOptions($dateCriterion),
        ]);
    }

    public function vendasExport(Request $request): StreamedResponse
    {
        $dateCriterion = $this->vendasDateCriterion($request);
        $sales = $this->vendasSalesForReport($request);
        $lines = $this->vendasResumoCollection(
            $sales,
            $request->get('vendas_servico'),
            $request->get('vendas_tecnico'),
            $dateCriterion,
        );

        $dataHeader = $this->vendasDataColunaLabel($dateCriterion);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Vendas');

        $headers = [
            $dataHeader,
            'Nº fatura',
            'Cliente',
            'NIF',
            'Técnico',
            'Serviço',
            'Origem',
            'Total (€)',
            'Taxas (€)',
            'Gorjeta (€)',
            'Estado fatura',
        ];
        $sheet->fromArray($headers, null, 'A1');

        $rowIndex = 2;
        foreach ($lines as $linha) {
            $categoria = trim((string) ($linha->categoria ?? ''));
            $categoria = $categoria !== '' && $categoria !== '—' ? $categoria : '';
            $servicoNomes = (string) ($linha->servico_nomes ?? $linha->servico ?? '—');
            $servicoCell = $categoria !== '' ? $categoria."\n".$servicoNomes : $servicoNomes;
            $faturaCell = $linha->numero_fatura ?: '—';
            if (! empty($linha->fatura_subtitulo)) {
                $faturaCell .= "\n".$linha->fatura_subtitulo;
            }

            $sheet->fromArray([
                [
                    $linha->data->format('d/m/Y'),
                    $faturaCell,
                    $linha->cliente,
                    $linha->nif,
                    $linha->tecnico,
                    $servicoCell,
                    $linha->origem_marcacao ?? '—',
                    round((float) $linha->valor + (float) ($linha->gorjeta ?? 0), 2),
                    round((float) ($linha->taxas ?? 0), 2),
                    round((float) ($linha->gorjeta ?? 0), 2),
                    ! empty($linha->is_anulado)
                        ? 'Anulada'
                        : (($linha->invoice_status ?? Sale::INVOICE_STATUS_FATURADO) === Sale::INVOICE_STATUS_RASCUNHO
                            ? 'Rascunho'
                            : 'Faturado'),
                ],
            ], null, 'A'.$rowIndex);
            $rowIndex++;
        }

        $totais = $this->vendasTotaisRodape($lines, $dateCriterion, $sales);
        $sheet->fromArray([
            [
                '',
                '',
                '',
                '',
                '',
                '',
                'Subtotal',
                round($totais['total_valor_com_gorjeta'], 2),
                round($totais['total_taxas'] ?? 0, 2),
                round($totais['total_gorjeta'], 2),
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
                '',
                'Total',
                round($totais['total_absoluto'], 2),
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
        return $this->vendasReportRunService->streamPdf(
            $this->vendasReportRunService->filtersFromRequest($request),
            $request,
        );
    }

    /**
     * @return array<string, string>
     */
    private function vendasPdfColumnOptions(string $dateCriterion): array
    {
        $labels = VendasReportPdfColumns::labels();
        $labels['data'] = $this->vendasDataColunaLabel($dateCriterion);

        return $labels;
    }

    /**
     * @return array<int, string>
     */
    private function vendasFiltrosResumo(Request $request): array
    {
        $desde = $request->get('vendas_desde') ?: $this->vendasDefaultDesde();
        $ate = $request->get('vendas_ate') ?: $this->vendasDefaultAte();

        $dateCriterion = $this->vendasDateCriterion($request);

        $lines = [
            'Período ('.mb_strtolower(VendasReportService::dateCriterionLabel($dateCriterion)).'): '
                .Carbon::parse($desde)->format('d/m/Y').' a '.Carbon::parse($ate)->format('d/m/Y'),
        ];

        if ($dateCriterion === VendasReportService::DATE_CRITERION_MARCACAO) {
            $lines[] = 'Marcações: apenas pagas (completo)';
        }

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
        return $this->vendasReportService->reportQuery([
            'desde' => $request->get('vendas_desde'),
            'ate' => $request->get('vendas_ate'),
            'cliente' => $request->get('vendas_cliente'),
            'servico' => $request->get('vendas_servico'),
            'tecnico' => $request->get('vendas_tecnico'),
            'estado' => $request->get('vendas_estado'),
            'data_criterio' => $this->vendasDateCriterion($request),
        ]);
    }

    /**
     * @return Collection<int, Sale>
     */
    private function vendasSalesForReport(Request $request): Collection
    {
        $sales = $this->vendasReportQuery($request)
            ->with(['client', 'calendarEvent.user', 'calendarEvent.eventServiceItems.extras.extra', 'settledEvents', 'items.service.category', 'items.extra', 'items.calendarEventService.service.category', 'items.calendarEventService.event.user'])
            ->get();

        if ($this->vendasDateCriterion($request) === VendasReportService::DATE_CRITERION_MARCACAO) {
            return $sales->sort(function (Sale $a, Sale $b) {
                $aTs = $a->calendarEvent?->start_at?->getTimestamp() ?? 0;
                $bTs = $b->calendarEvent?->start_at?->getTimestamp() ?? 0;
                if ($aTs !== $bTs) {
                    return $bTs <=> $aTs;
                }

                return $b->id <=> $a->id;
            })->values();
        }

        return $sales->sort(function (Sale $a, Sale $b) {
            $aDate = $a->data_emissao?->format('Y-m-d') ?? '';
            $bDate = $b->data_emissao?->format('Y-m-d') ?? '';
            if ($aDate !== $bDate) {
                return $bDate <=> $aDate;
            }

            return $b->id <=> $a->id;
        })->values();
    }

    private function vendasDateCriterion(Request $request): string
    {
        return VendasReportService::resolveDateCriterion($request->get('vendas_data_criterio'));
    }

    private function vendasDataColunaLabel(string $dateCriterion): string
    {
        return $dateCriterion === VendasReportService::DATE_CRITERION_MARCACAO
            ? 'Data marcação'
            : 'Data emissão';
    }

    /**
     * @param  Collection<int, object>  $lines
     * @return array{total_valor: float, total_valor_com_gorjeta: float, total_gorjeta: float, total_taxas: float, total_absoluto: float, num_vendas: int, total_servicos: int, total_desconto: float, total_divida: float}
     */
    private function vendasTotaisRodape(Collection $lines, ?string $dateCriterion = null, ?Collection $sales = null): array
    {
        return $this->vendasReportService->totaisRodape($lines, $dateCriterion, $sales);
    }

    /**
     * @return Collection<int, object>
     */
    private function vendasResumoCollection(Collection $sales, ?string $vendasServico, ?string $vendasTecnico = null, ?string $dateCriterion = null): Collection
    {
        return $this->vendasReportService->resumoCollection($sales, $vendasServico, $vendasTecnico, $dateCriterion);
    }

    private function vendasClientesOpts(): Collection
    {
        return $this->vendasReportService->clientesOpts();
    }

    private function vendasServicosOpts(): Collection
    {
        return $this->vendasReportService->servicosOpts();
    }

    private function marcacoesDefaultDesde(): string
    {
        return now()->copy()->startOfMonth()->toDateString();
    }

    private function marcacoesDefaultAte(): string
    {
        return now()->copy()->endOfMonth()->toDateString();
    }

    private function vendasDefaultDesde(): string
    {
        return now()->copy()->startOfMonth()->toDateString();
    }

    private function vendasDefaultAte(): string
    {
        return now()->copy()->endOfMonth()->toDateString();
    }

    /**
     * Prestadores de serviços activos (filtro «Técnico» nos relatórios).
     *
     * @return Collection<int, User>
     */
    private function membrosOptsForRelatorios(): Collection
    {
        return User::activeServiceProviders(current_store_id())
            ->select('users.id', 'users.name')
            ->orderBy('users.name')
            ->get();
    }

    public function comissoes(Request $request): View
    {
        $report = $this->comissoesReportData($request);

        $page = max(1, (int) $request->get('page', 1));
        $perPage = 100;
        $slice = $report['lines']->slice(($page - 1) * $perPage, $perPage)->values();

        $linhas = new LengthAwarePaginator(
            $slice,
            $report['lines']->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'pageName' => 'page',
            ]
        );
        $linhas->withQueryString();

        return view('relatorios.comissoes', [
            'pageTitle' => 'Relatórios — Comissões',
            'linhas' => $linhas,
            'comissoesDesde' => $report['filters']['desde'],
            'comissoesAte' => $report['filters']['ate'],
            'comissoesServico' => $request->get('comissoes_servico'),
            'comissoesTecnico' => $request->get('comissoes_tecnico'),
            'comissoesEstado' => $request->get('comissoes_estado'),
            'comissoesCliente' => $request->get('comissoes_cliente'),
            'servicosOpts' => $this->comissoesReportService->servicosOpts(),
            'tecnicosOpts' => $this->membrosOptsForRelatorios(),
            'clientesOpts' => $this->comissoesReportService->clientesOpts(),
            'comissoesTotais' => $report['totais'],
            'comissoesTotalHistorico' => $report['usesHistoricalFooter'],
            'comissoesPdfColumnOptions' => ComissoesReportPdfColumns::labels(),
        ]);
    }

    public function comissoesExport(Request $request): StreamedResponse
    {
        $report = $this->comissoesReportData($request);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Comissões');

        $headers = [
            'Data venda',
            'N.º fatura',
            'Colaborador(a)',
            'Cliente',
            'Serviço',
            'Valor serviço c/ IVA (€)',
            'Valor serviço s/ IVA (€)',
            'Comissão (%)',
            'Valor comissão c/ IVA (€)',
            'Valor comissão s/ IVA (€)',
        ];
        $sheet->fromArray($headers, null, 'A1');

        $rowIndex = 2;
        foreach ($report['lines'] as $linha) {
            $sheet->fromArray([
                [
                    $linha->data_emissao ? DateTimeDisplay::businessDate($linha->data_emissao) : '',
                    $linha->numero_fatura ?? '',
                    $linha->tecnico,
                    $linha->cliente,
                    $linha->servico,
                    round((float) $linha->valor_com_iva, 2),
                    round((float) $linha->valor_sem_iva, 2),
                    $linha->comissao_taxa ?? '',
                    round((float) $linha->comissao_com_iva, 2),
                    round((float) $linha->comissao_sem_iva, 2),
                ],
            ], null, 'A'.$rowIndex);
            $rowIndex++;
        }

        $totais = $report['totais'];
        if ($report['usesHistoricalFooter']) {
            $sheet->fromArray([
                [
                    'Nota: total c/ IVA alinhado ao Zappy (até 31/05/2026). Linhas = cálculo CRM.',
                ],
            ], null, 'A'.$rowIndex);
            $rowIndex++;
        }

        $sheet->fromArray([
            [
                '',
                '',
                '',
                '',
                'Total comissões a pagar (c/ IVA)',
                '',
                '',
                '',
                round((float) ($totais['total_comissao_com_iva'] ?? 0), 2),
                round((float) ($totais['total_comissao_sem_iva'] ?? 0), 2),
            ],
        ], null, 'A'.$rowIndex);

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'comissoes_'.now()->format('Y-m-d_His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function comissoesPdf(Request $request)
    {
        $report = $this->comissoesReportData($request);

        $pdfColumns = ComissoesReportPdfColumns::resolveFromRequest($request);

        $pdf = Pdf::loadView('relatorios.pdf.comissoes', [
            'linhas' => $report['lines'],
            'filtrosLinhas' => $this->comissoesFiltrosResumo($request),
            'appName' => config('app.name'),
            'totalLinhas' => $report['lines']->count(),
            'comissoesTotais' => $report['totais'],
            'usesHistoricalFooter' => $report['usesHistoricalFooter'],
            'comissoesComIva' => $this->comissoesComIvaPreference($request),
            'pdfColumns' => $pdfColumns,
            'pdfColumnLabels' => ComissoesReportPdfColumns::labels(),
        ])->setPaper('a4', ComissoesReportPdfColumns::resolveOrientationFromRequest($request));

        $filename = 'comissoes_'.now()->format('Y-m-d_His').'.pdf';

        return $pdf->stream($filename);
    }

    /**
     * @return array{
     *   filters: array{desde: string, ate: string, cliente: mixed, servico: mixed, tecnico: mixed|null, estado: ?string},
     *   lines: Collection<int, object>,
     *   totais: array{total_comissao_com_iva: float, total_comissao_sem_iva: float},
     *   usesHistoricalFooter: bool
     * }
     */
    private function comissoesReportData(Request $request): array
    {
        $filters = $this->comissoesFiltersFromRequest($request);
        $filters['tecnico'] = TechnicianFilterUserId::resolve($filters['tecnico']);

        $sales = $this->comissoesReportService->salesForReport($filters);
        $servicoFilter = $filters['servico'] !== null && $filters['servico'] !== ''
            ? (int) $filters['servico']
            : null;
        $tecnicoFilter = $filters['tecnico'];
        $lines = $this->comissoesReportService->linesCollection($sales, $servicoFilter, $tecnicoFilter);

        return [
            'filters' => $filters,
            'lines' => $lines,
            'totais' => $this->comissoesReportService->totaisRodape($lines, $filters),
            'usesHistoricalFooter' => $this->comissoesReportService->footerUsesHistoricalOverride($filters),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function comissoesFiltrosResumo(Request $request): array
    {
        $desde = $this->normalizeRelatorioDate($request->get('comissoes_desde')) ?: $this->marcacoesDefaultDesde();
        $ate = $this->normalizeRelatorioDate($request->get('comissoes_ate')) ?: $this->marcacoesDefaultAte();

        $lines = [
            'Período: '.Carbon::parse($desde)->format('d/m/Y').' a '.Carbon::parse($ate)->format('d/m/Y'),
        ];

        if ($cid = $request->get('comissoes_cliente')) {
            $lines[] = 'Cliente: '.(Client::query()->forStore(current_store_id())->find($cid)?->name ?? '—');
        }
        if ($sid = $request->get('comissoes_servico')) {
            $lines[] = 'Serviço: '.(Service::query()->forStore(current_store_id())->find($sid)?->name ?? '—');
        }
        if ($tid = $request->get('comissoes_tecnico')) {
            $lines[] = 'Colaborador(a): '.(User::activeServiceProviders(current_store_id())->find($tid)?->name ?? '—');
        }

        return $lines;
    }

    private function comissoesComIvaPreference(Request $request): bool
    {
        $param = $request->query('comissoes_com_iva');
        if ($param === '0') {
            return false;
        }
        if ($param === '1') {
            return true;
        }

        return true;
    }

    /**
     * @return array{desde: string, ate: string, cliente: mixed, servico: mixed, tecnico: mixed, estado: ?string}
     */
    private function comissoesFiltersFromRequest(Request $request): array
    {
        $desde = $this->normalizeRelatorioDate($request->get('comissoes_desde')) ?: $this->marcacoesDefaultDesde();
        $ate = $this->normalizeRelatorioDate($request->get('comissoes_ate')) ?: $this->marcacoesDefaultAte();

        return [
            'desde' => $desde,
            'ate' => $ate,
            'cliente' => $request->get('comissoes_cliente'),
            'servico' => $request->get('comissoes_servico'),
            'tecnico' => $request->get('comissoes_tecnico'),
            'estado' => $request->get('comissoes_estado'),
        ];
    }

    private function normalizeRelatorioDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $value)) {
            return $value;
        }

        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})#', $value, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        try {
            return \Carbon\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public function bookingFunnel(Request $request): View
    {
        $storeId = current_store_id();
        $tab = $this->bookingFunnelReportService->resolveTab((string) $request->query('tab', BookingFunnelReportService::TAB_SMS_PENDING));
        $rows = $this->bookingFunnelReportService->paginatedTabQuery($tab, $storeId);
        $authCodeClients = in_array($tab, [
            BookingFunnelReportService::TAB_SMS_PENDING,
            BookingFunnelReportService::TAB_OTP_FAILED,
        ], true)
            ? $this->bookingFunnelReportService->clientsForAuthCodes($rows->getCollection(), $storeId)
            : [];

        return view('relatorios.booking-funnel', [
            'pageTitle' => 'Relatórios — Funil Booking',
            'activeTab' => $tab,
            'summaryCounts' => $this->bookingFunnelReportService->summaryCounts($storeId),
            'rows' => $rows,
            'authCodeClients' => $authCodeClients,
            'storeTimezone' => StoreBusinessTime::timezoneForStore($storeId),
            'funnelService' => $this->bookingFunnelReportService,
        ]);
    }

    public function sms(Request $request): View
    {
        $storeId = current_store_id();
        $today = StoreBusinessTime::nowForStore($storeId)->startOfDay();
        $availableYears = $this->smsReportService->availableYears($storeId);
        $year = (int) $request->get('year', $today->year);
        $year = max($availableYears[0] ?? $today->year, min($today->year, $year));
        $month = max(1, min(12, (int) $request->get('month', $today->month)));
        if ($year === $today->year && $month > $today->month) {
            $month = $today->month;
        }

        $messages = $this->smsReportService->reportQuery($storeId, $year, $month)
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('relatorios.sms', [
            'pageTitle' => 'Relatórios — SMS',
            'messages' => $messages,
            'summaryCounts' => $this->smsReportService->summaryCounts($storeId),
            'month' => $month,
            'year' => $year,
            'monthOptions' => $this->smsReportService->monthOptions(),
            'availableYears' => $availableYears,
            'periodLabel' => $this->smsReportService->periodLabel($year, $month),
            'typeLabels' => SmsMessage::typeLabels(),
            'storeTimezone' => StoreBusinessTime::timezoneForStore($storeId),
        ]);
    }
}
