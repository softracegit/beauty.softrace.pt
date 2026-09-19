<?php

namespace App\Http\Controllers;

use App\Exceptions\AppointmentReactivationException;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\SmsMessage;
use App\Models\Store;
use App\Models\User;
use App\Services\AppointmentReactivationService;
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
use App\Support\StoreContextPreference;
use App\Support\VendasReportPdfColumns;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
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
        private readonly AppointmentReactivationService $appointmentReactivationService,
        private readonly \App\Services\OrganizationMetricsService $organizationMetricsService,
    ) {}

    public function organizacao(Request $request): View
    {
        $ctx = $this->organizacaoContext($request);
        $evolucaoMode = $request->input('evolucao');
        $metrics = $this->organizationMetricsService->summarize(
            $ctx['storeIds'],
            $ctx['desde'],
            $ctx['ate'],
            is_string($evolucaoMode) ? $evolucaoMode : null,
        );

        return view('relatorios.organizacao', [
            'stores' => $ctx['allStores'],
            'desde' => $ctx['desde'],
            'ate' => $ctx['ate'],
            'lojasScope' => $ctx['scope'],
            'selectedStoreIds' => $ctx['storeIds'],
            'evolucaoMode' => $metrics['evolucao']['mode'] ?? 'diaria',
            'metrics' => $metrics,
        ]);
    }

    public function organizacaoExport(Request $request): StreamedResponse
    {
        $ctx = $this->organizacaoContext($request);
        $evolucaoMode = $request->input('evolucao');
        $metrics = $this->organizationMetricsService->summarize(
            $ctx['storeIds'],
            $ctx['desde'],
            $ctx['ate'],
            is_string($evolucaoMode) ? $evolucaoMode : null,
        );

        $filename = 'resumo_empresa_'.$ctx['desde'].'_'.$ctx['ate'].'.csv';

        return response()->streamDownload(function () use ($metrics, $ctx) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $sep = ';';

            fputcsv($out, ['Resumo empresa', $ctx['desde'].' a '.$ctx['ate']], $sep);
            fputcsv($out, [], $sep);
            fputcsv($out, ['Indicador', 'Valor'], $sep);
            fputcsv($out, ['Faturação', number_format((float) $metrics['faturacao_total'], 2, ',', '')], $sep);
            fputcsv($out, ['Vendas pagas', (string) $metrics['num_vendas']], $sep);
            fputcsv($out, ['Ticket médio', number_format((float) $metrics['ticket_medio'], 2, ',', '')], $sep);
            fputcsv($out, ['Taxa conclusão %', number_format((float) $metrics['taxa_conclusao'], 1, ',', '')], $sep);
            fputcsv($out, ['No-shows total', (string) $metrics['noshows_total']], $sep);
            fputcsv($out, ['Faltou', (string) $metrics['faltou_total']], $sep);
            fputcsv($out, ['Cancelado', (string) $metrics['cancelado_total']], $sep);
            fputcsv($out, ['Anulado', (string) $metrics['anulado_total']], $sep);
            fputcsv($out, ['Ocupação %', number_format((float) $metrics['taxa_ocupacao'], 1, ',', '')], $sep);
            fputcsv($out, ['Rascunhos', (string) $metrics['rascunhos_total']], $sep);
            fputcsv($out, ['SMS falhados', (string) $metrics['sms_falhados_total']], $sep);
            fputcsv($out, [], $sep);

            fputcsv($out, [
                'Loja', 'Previsto', 'Feito', 'Por fazer', 'Ocupação %', 'Faltou', 'Cancelado', 'Anulado', 'No-shows %', 'Conclusão %', 'Clientes', 'Caixa', 'Rascunhos', 'SMS fail',
            ], $sep);
            foreach ($metrics['por_loja'] as $row) {
                fputcsv($out, [
                    $row->nome,
                    number_format((float) $row->previsto, 2, ',', ''),
                    number_format((float) $row->vendas_feitas, 2, ',', ''),
                    number_format((float) $row->por_fazer, 2, ',', ''),
                    number_format((float) $row->taxa_ocupacao, 1, ',', ''),
                    (string) $row->faltou,
                    (string) $row->cancelado,
                    (string) $row->anulado,
                    number_format((float) $row->taxa_noshow, 1, ',', ''),
                    number_format((float) $row->taxa_conclusao, 1, ',', ''),
                    (string) $row->clientes_unicos,
                    $row->caixa_label,
                    (string) $row->rascunhos,
                    (string) $row->sms_falhados,
                ], $sep);
            }

            fputcsv($out, [], $sep);
            fputcsv($out, ['Top serviços', 'Qtd', 'Receita'], $sep);
            foreach ($metrics['top_servicos'] as $svc) {
                fputcsv($out, [$svc->nome, (string) $svc->qtd, number_format((float) $svc->receita, 2, ',', '')], $sep);
            }

            fputcsv($out, [], $sep);
            fputcsv($out, ['Top categorias', 'Qtd', 'Receita'], $sep);
            foreach ($metrics['top_categorias'] as $cat) {
                fputcsv($out, [$cat->nome, (string) $cat->qtd, number_format((float) $cat->receita, 2, ',', '')], $sep);
            }

            fputcsv($out, [], $sep);
            fputcsv($out, ['Técnico', 'Comissão c/ IVA'], $sep);
            foreach ($metrics['comissoes_tecnicos'] as $tec) {
                fputcsv($out, [$tec->nome, number_format((float) $tec->comissao, 2, ',', '')], $sep);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function organizacaoPdf(Request $request)
    {
        $ctx = $this->organizacaoContext($request);
        $evolucaoMode = $request->input('evolucao');
        $metrics = $this->organizationMetricsService->summarize(
            $ctx['storeIds'],
            $ctx['desde'],
            $ctx['ate'],
            is_string($evolucaoMode) ? $evolucaoMode : null,
        );

        $pdf = Pdf::loadView('relatorios.pdf.organizacao', [
            'metrics' => $metrics,
            'desde' => $ctx['desde'],
            'ate' => $ctx['ate'],
            'appName' => config('app.name'),
            'lojasScope' => $ctx['scope'],
            'storeNames' => $ctx['allStores']->whereIn('id', $ctx['storeIds'])->pluck('name')->values()->all(),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('resumo_empresa_'.$ctx['desde'].'_'.$ctx['ate'].'.pdf');
    }

    /**
     * @return array{allStores: \Illuminate\Support\Collection, desde: string, ate: string, scope: string, storeIds: list<int>}
     */
    private function organizacaoContext(Request $request): array
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isAdmin(), 403);

        $orgId = (int) $user->organization_id;
        $allStores = \App\Models\Store::query()
            ->where('organization_id', $orgId)
            ->orderBy('name')
            ->get();

        $desde = $request->input('desde') ?: now()->copy()->startOfMonth()->toDateString();
        $ate = $request->input('ate') ?: now()->copy()->endOfMonth()->toDateString();

        $scope = (string) $request->input('lojas_scope', 'todas');
        $selectedIds = collect($request->input('store_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        $allowedIds = $allStores->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($scope === 'actual') {
            $storeIds = in_array(current_store_id(), $allowedIds, true) ? [current_store_id()] : $allowedIds;
        } elseif ($scope === 'subset' && $selectedIds !== []) {
            $storeIds = array_values(array_intersect($selectedIds, $allowedIds));
        } else {
            $scope = 'todas';
            $storeIds = $allowedIds;
        }

        return [
            'allStores' => $allStores,
            'desde' => $desde,
            'ate' => $ate,
            'scope' => $scope,
            'storeIds' => $storeIds,
        ];
    }

    public function marcacoes(Request $request): View
    {
        $storeCtx = $this->resolveReportStoreContext($request);
        $storeIds = $storeCtx['store_ids'];

        $marcacoes = $this->marcacoesReportQuery($request, $storeIds)
            ->with(['user', 'client', 'personalTimeType', 'eventServiceItems.service.category', 'eventServiceItems.extras.extra'])
            ->orderByDesc('start_at')
            ->paginate(100)
            ->withQueryString();

        $servicosOpts = Service::query()
            ->join('calendar_event_services', 'services.id', '=', 'calendar_event_services.service_id')
            ->join('calendar_events', 'calendar_events.id', '=', 'calendar_event_services.calendar_event_id')
            ->whereIn('calendar_events.store_id', $storeIds)
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->select('services.id', 'services.name')
            ->distinct()
            ->orderBy('services.name')
            ->get();

        $tecnicosOpts = $this->membrosOptsForRelatorios($storeIds);

        $clientesOpts = Client::query()
            ->forOrganization(current_organization_id())
            ->join('calendar_events', 'calendar_events.client_id', '=', 'clients.id')
            ->whereIn('calendar_events.store_id', $storeIds)
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->select('clients.id', 'clients.name')
            ->distinct()
            ->orderBy('clients.name')
            ->get();

        $marcacoesDesde = $request->get('marcacoes_desde') ?: $this->marcacoesDefaultDesde();
        $marcacoesAte = $request->get('marcacoes_ate') ?: $this->marcacoesDefaultAte();

        return view('relatorios.marcacoes', [
            'pageTitle' => 'Relatórios — Marcações',
            'reportStoreFilter' => $storeCtx,
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
            'marcacoesTotais' => $this->marcacoesReportTotals($request, $storeIds),
            'marcacoesPdfColumnOptions' => MarcacoesReportPdfColumns::labels(),
        ]);
    }

    public function marcacoesReativarPreview(CalendarEvent $calendarEvent): JsonResponse
    {
        $this->assertAdminCanReactivateMarcacao($calendarEvent);

        $calendarEvent->loadMissing('client');

        $status = (string) ($calendarEvent->status ?? '');
        $statusLabel = CalendarEvent::statuses()[$status] ?? $status;

        if (! in_array($status, [CalendarEvent::STATUS_CANCELADO, CalendarEvent::STATUS_FALTOU], true)) {
            return response()->json([
                'success' => true,
                'event_id' => $calendarEvent->id,
                'status' => $status,
                'status_label' => $statusLabel,
                'can_reactivate' => false,
                'blockers' => ['Só é possível reativar marcações canceladas ou com falta.'],
                'start_at' => DateTimeDisplay::marcacao($calendarEvent->start_at, (int) ($calendarEvent->store_id ?? 0) ?: null),
                'client_name' => $calendarEvent->client?->name,
            ]);
        }

        $blockers = $this->appointmentReactivationService->blockers($calendarEvent);

        return response()->json([
            'success' => true,
            'event_id' => $calendarEvent->id,
            'status' => $status,
            'status_label' => $statusLabel,
            'can_reactivate' => $blockers === [],
            'blockers' => $blockers,
            'start_at' => DateTimeDisplay::marcacao($calendarEvent->start_at, (int) ($calendarEvent->store_id ?? 0) ?: null),
            'client_name' => $calendarEvent->client?->name,
        ]);
    }

    public function marcacoesReativar(Request $request, CalendarEvent $calendarEvent): JsonResponse
    {
        $this->assertAdminCanReactivateMarcacao($calendarEvent);

        $validated = $request->validate([
            'reactivation_reason' => ['nullable', 'string', 'max:1000'],
            'notify_client' => ['sometimes', 'boolean'],
        ]);

        try {
            $result = $this->appointmentReactivationService->reactivate($calendarEvent, [
                'reactivation_reason' => $validated['reactivation_reason'] ?? null,
                'notify_client' => (bool) ($validated['notify_client'] ?? false),
            ]);
        } catch (AppointmentReactivationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'blockers' => $e->blockers !== [] ? $e->blockers : [$e->getMessage()],
                'reason_code' => $e->reasonCode,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Marcação reativada com sucesso.',
            'client_notified' => $result->clientNotified,
            'event_id' => $result->event->id,
            'status' => $result->event->status,
        ]);
    }

    private function assertAdminCanReactivateMarcacao(CalendarEvent $calendarEvent): void
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $user->isAdmin()) {
            abort(403);
        }

        if (($calendarEvent->event_type ?? '') !== CalendarEvent::TYPE_MARCACAO) {
            abort(404);
        }
    }

    public function marcacoesExport(Request $request): StreamedResponse
    {
        $storeCtx = $this->resolveReportStoreContext($request);
        $storeIds = $storeCtx['store_ids'];

        $events = $this->marcacoesReportQuery($request, $storeIds)
            ->with(['user', 'client', 'personalTimeType', 'eventServiceItems.service.category', 'eventServiceItems.extras.extra'])
            ->orderByDesc('start_at')
            ->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Marcações');

        $sheet->fromArray([$this->relatorioLojaFiltroLine($storeCtx)], null, 'A1');

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
        $sheet->fromArray($headers, null, 'A2');

        $rowIndex = 3;
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
        $storeCtx = $this->resolveReportStoreContext($request);
        $storeIds = $storeCtx['store_ids'];

        $events = $this->marcacoesReportQuery($request, $storeIds)
            ->with(['user', 'client', 'personalTimeType', 'eventServiceItems.service.category', 'eventServiceItems.extras'])
            ->orderByDesc('start_at')
            ->get();

        $pdfColumns = MarcacoesReportPdfColumns::resolveFromRequest($request);

        $pdf = Pdf::loadView('relatorios.pdf.marcacoes', [
            'marcacoes' => $events,
            'filtrosLinhas' => $this->marcacoesFiltrosResumo($request, $storeCtx),
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
     * @param  array{store_id: ?int, scope: string, store_ids: list<int>}  $storeCtx
     * @return array<int, string>
     */
    private function marcacoesFiltrosResumo(Request $request, array $storeCtx): array
    {
        $desde = $request->get('marcacoes_desde') ?: $this->marcacoesDefaultDesde();
        $ate = $request->get('marcacoes_ate') ?: $this->marcacoesDefaultAte();
        $storeIds = $storeCtx['store_ids'];

        $lines = [
            $this->relatorioLojaFiltroLine($storeCtx),
            'Período: '.Carbon::parse($desde)->format('d/m/Y').' a '.Carbon::parse($ate)->format('d/m/Y'),
        ];

        if ($cid = $request->get('marcacoes_cliente')) {
            $lines[] = 'Cliente: '.(Client::query()->forOrganization(current_organization_id())->find($cid)?->name ?? '—');
        }
        if ($sid = $request->get('marcacoes_servico')) {
            $lines[] = 'Serviço: '.(Service::query()->forOrganization(current_organization_id())->find($sid)?->name ?? '—');
        }
        if ($tid = $request->get('marcacoes_tecnico')) {
            $lines[] = 'Técnico: '.(User::activeStaff($storeIds)->find($tid)?->name ?? '—');
        }
        $est = MarcacoesReportEstadoFilter::resolve($request->get('marcacoes_estado'));
        $lines[] = 'Estado: '.MarcacoesReportEstadoFilter::label($est);

        return $lines;
    }

    /**
     * Query base do relatório de marcações (mesmos filtros na listagem e na exportação).
     *
     * @param  list<int>  $storeIds
     */
    private function marcacoesReportQuery(Request $request, array $storeIds): Builder
    {
        $marcacoesDesde = $request->get('marcacoes_desde') ?: $this->marcacoesDefaultDesde();
        $marcacoesAte = $request->get('marcacoes_ate') ?: $this->marcacoesDefaultAte();
        $marcacoesServico = $request->get('marcacoes_servico');
        $marcacoesTecnico = $request->get('marcacoes_tecnico');
        $marcacoesEstado = MarcacoesReportEstadoFilter::resolve($request->get('marcacoes_estado'));
        $marcacoesCliente = $request->get('marcacoes_cliente');

        $marcacoesQuery = MarcacoesReportEstadoFilter::apply(
            CalendarEvent::query()->whereIn('store_id', $storeIds),
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
     * @param  list<int>  $storeIds
     * @return array{preco_total: float, servicos_count: int}
     */
    private function marcacoesReportTotals(Request $request, array $storeIds): array
    {
        $eventIds = $this->marcacoesReportQuery($request, $storeIds)->select('calendar_events.id');

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
        $storeCtx = $this->resolveReportStoreContext($request);
        $storeIds = $storeCtx['store_ids'];
        $dateCriterion = $this->vendasDateCriterion($request);
        $sales = $this->vendasSalesForReport($request, $storeIds);

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
            'reportStoreFilter' => $storeCtx,
            'vendas' => $vendas,
            'vendasDesde' => $vendasDesde,
            'vendasAte' => $vendasAte,
            'vendasCliente' => $request->get('vendas_cliente'),
            'vendasServico' => $request->get('vendas_servico'),
            'vendasTecnico' => $request->get('vendas_tecnico'),
            'vendasEstado' => $request->get('vendas_estado'),
            'vendasDataCriterio' => $dateCriterion,
            'vendasDataColunaLabel' => $this->vendasDataColunaLabel($dateCriterion),
            'clientesOpts' => $this->vendasClientesOpts($storeIds),
            'servicosOpts' => $this->vendasServicosOpts($storeIds),
            'tecnicosOpts' => $this->membrosOptsForRelatorios($storeIds),
            'vendasTotais' => $this->vendasTotaisRodape($allLines, $dateCriterion, $sales),
            'vendasPdfColumnOptions' => $this->vendasPdfColumnOptions($dateCriterion),
        ]);
    }

    public function vendasExport(Request $request): StreamedResponse
    {
        $storeCtx = $this->resolveReportStoreContext($request);
        $storeIds = $storeCtx['store_ids'];
        $dateCriterion = $this->vendasDateCriterion($request);
        $sales = $this->vendasSalesForReport($request, $storeIds);
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

        $sheet->fromArray([$this->relatorioLojaFiltroLine($storeCtx)], null, 'A1');

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
        $sheet->fromArray($headers, null, 'A2');

        $rowIndex = 3;
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
        $storeCtx = $this->resolveReportStoreContext($request);
        $filters = $this->vendasReportRunService->filtersFromRequest($request);
        $filters['store_ids'] = $storeCtx['store_ids'];
        $filters['store_scope'] = $storeCtx['scope'];

        return $this->vendasReportRunService->streamPdf($filters, $request);
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
     * @param  array{store_id: ?int, scope: string, store_ids: list<int>}  $storeCtx
     * @return array<int, string>
     */
    private function vendasFiltrosResumo(Request $request, array $storeCtx): array
    {
        $desde = $request->get('vendas_desde') ?: $this->vendasDefaultDesde();
        $ate = $request->get('vendas_ate') ?: $this->vendasDefaultAte();
        $storeIds = $storeCtx['store_ids'];

        $dateCriterion = $this->vendasDateCriterion($request);

        $lines = [
            $this->relatorioLojaFiltroLine($storeCtx),
            'Período ('.mb_strtolower(VendasReportService::dateCriterionLabel($dateCriterion)).'): '
                .Carbon::parse($desde)->format('d/m/Y').' a '.Carbon::parse($ate)->format('d/m/Y'),
        ];

        if ($dateCriterion === VendasReportService::DATE_CRITERION_MARCACAO) {
            $lines[] = 'Marcações: apenas pagas (completo)';
        }

        if ($cid = $request->get('vendas_cliente')) {
            $lines[] = 'Cliente: '.(Client::query()->forOrganization(current_organization_id())->find($cid)?->name ?? '—');
        }
        if ($sid = $request->get('vendas_servico')) {
            $lines[] = 'Serviço: '.(Service::query()->forOrganization(current_organization_id())->find($sid)?->name ?? '—');
        }
        if ($tid = $request->get('vendas_tecnico')) {
            $lines[] = 'Técnico: '.(User::activeStaff($storeIds)->find($tid)?->name ?? '—');
        }
        if ($est = $request->get('vendas_estado')) {
            $label = $est === Sale::INVOICE_STATUS_RASCUNHO ? 'Rascunho' : ($est === Sale::INVOICE_STATUS_FATURADO ? 'Faturado' : $est);
            $lines[] = 'Estado da fatura: '.$label;
        } else {
            $lines[] = 'Estado da fatura: Faturado e Rascunho';
        }

        return $lines;
    }

    /**
     * @param  list<int>  $storeIds
     */
    private function vendasReportQuery(Request $request, array $storeIds): Builder
    {
        return $this->vendasReportService->reportQuery([
            'desde' => $request->get('vendas_desde'),
            'ate' => $request->get('vendas_ate'),
            'cliente' => $request->get('vendas_cliente'),
            'servico' => $request->get('vendas_servico'),
            'tecnico' => $request->get('vendas_tecnico'),
            'estado' => $request->get('vendas_estado'),
            'data_criterio' => $this->vendasDateCriterion($request),
            'store_ids' => $storeIds,
        ]);
    }

    /**
     * @param  list<int>  $storeIds
     * @return Collection<int, Sale>
     */
    private function vendasSalesForReport(Request $request, array $storeIds): Collection
    {
        $sales = $this->vendasReportQuery($request, $storeIds)
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
        return VendasReportService::defaultDateCriterion();
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

    /**
     * @param  list<int>  $storeIds
     */
    private function vendasClientesOpts(array $storeIds): Collection
    {
        return $this->vendasReportService->clientesOpts($storeIds);
    }

    /**
     * @param  list<int>  $storeIds
     */
    private function vendasServicosOpts(array $storeIds): Collection
    {
        return $this->vendasReportService->servicosOpts($storeIds);
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
     * @param  list<int>  $storeIds
     * @return Collection<int, User>
     */
    private function membrosOptsForRelatorios(array $storeIds): Collection
    {
        return User::activeServiceProviders($storeIds)
            ->select('users.id', 'users.name')
            ->orderBy('users.name')
            ->get();
    }

    public function comissoes(Request $request): View
    {
        $storeCtx = $this->resolveReportStoreContext($request);
        $report = $this->comissoesReportData($request, $storeCtx);

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
            'reportStoreFilter' => $storeCtx,
            'linhas' => $linhas,
            'comissoesDesde' => $report['filters']['desde'],
            'comissoesAte' => $report['filters']['ate'],
            'comissoesServico' => $request->get('comissoes_servico'),
            'comissoesTecnico' => $request->get('comissoes_tecnico'),
            'comissoesEstado' => $request->get('comissoes_estado'),
            'comissoesCliente' => $request->get('comissoes_cliente'),
            'servicosOpts' => $this->comissoesReportService->servicosOpts($storeCtx['store_ids']),
            'tecnicosOpts' => $this->membrosOptsForRelatorios($storeCtx['store_ids']),
            'clientesOpts' => $this->comissoesReportService->clientesOpts($storeCtx['store_ids']),
            'comissoesTotais' => $report['totais'],
            'comissoesTotalHistorico' => $report['usesHistoricalFooter'],
            'comissoesPdfColumnOptions' => ComissoesReportPdfColumns::labels(),
        ]);
    }

    public function comissoesExport(Request $request): StreamedResponse
    {
        $storeCtx = $this->resolveReportStoreContext($request);
        $report = $this->comissoesReportData($request, $storeCtx);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Comissões');

        $sheet->fromArray([$this->relatorioLojaFiltroLine($storeCtx)], null, 'A1');

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
        $sheet->fromArray($headers, null, 'A2');

        $rowIndex = 3;
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
        $storeCtx = $this->resolveReportStoreContext($request);
        $report = $this->comissoesReportData($request, $storeCtx);

        $pdfColumns = ComissoesReportPdfColumns::resolveFromRequest($request);

        $pdf = Pdf::loadView('relatorios.pdf.comissoes', [
            'linhas' => $report['lines'],
            'filtrosLinhas' => $this->comissoesFiltrosResumo($request, $storeCtx),
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
     * @param  array{store_id: ?int, scope: string, store_ids: list<int>}  $storeCtx
     * @return array{
     *   filters: array{desde: string, ate: string, cliente: mixed, servico: mixed, tecnico: mixed|null, estado: ?string, store_ids: list<int>},
     *   lines: Collection<int, object>,
     *   totais: array{total_comissao_com_iva: float, total_comissao_sem_iva: float},
     *   usesHistoricalFooter: bool
     * }
     */
    private function comissoesReportData(Request $request, array $storeCtx): array
    {
        $filters = $this->comissoesFiltersFromRequest($request);
        $filters['store_ids'] = $storeCtx['store_ids'];
        $filters['tecnico'] = TechnicianFilterUserId::resolve($filters['tecnico']);

        $sales = $this->comissoesReportService->salesForReport($filters);
        $servicoFilter = $filters['servico'] !== null && $filters['servico'] !== ''
            ? (int) $filters['servico']
            : null;
        $tecnicoFilter = $filters['tecnico'];
        $lines = $this->comissoesReportService->linesCollection(
            $sales,
            $servicoFilter,
            $tecnicoFilter,
            $storeCtx['store_ids'],
        );

        return [
            'filters' => $filters,
            'lines' => $lines,
            'totais' => $this->comissoesReportService->totaisRodape($lines, $filters),
            'usesHistoricalFooter' => $this->comissoesReportService->footerUsesHistoricalOverride($filters),
        ];
    }

    /**
     * @param  array{store_id: ?int, scope: string, store_ids: list<int>}  $storeCtx
     * @return array<int, string>
     */
    private function comissoesFiltrosResumo(Request $request, array $storeCtx): array
    {
        $desde = $this->normalizeRelatorioDate($request->get('comissoes_desde')) ?: $this->marcacoesDefaultDesde();
        $ate = $this->normalizeRelatorioDate($request->get('comissoes_ate')) ?: $this->marcacoesDefaultAte();
        $storeIds = $storeCtx['store_ids'];

        $lines = [
            $this->relatorioLojaFiltroLine($storeCtx),
            'Período: '.Carbon::parse($desde)->format('d/m/Y').' a '.Carbon::parse($ate)->format('d/m/Y'),
        ];

        if ($cid = $request->get('comissoes_cliente')) {
            $lines[] = 'Cliente: '.(Client::query()->forOrganization(current_organization_id())->find($cid)?->name ?? '—');
        }
        if ($sid = $request->get('comissoes_servico')) {
            $lines[] = 'Serviço: '.(Service::query()->forOrganization(current_organization_id())->find($sid)?->name ?? '—');
        }
        if ($tid = $request->get('comissoes_tecnico')) {
            $lines[] = 'Colaborador(a): '.(User::activeServiceProviders($storeIds)->find($tid)?->name ?? '—');
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
        $storeCtx = $this->resolveReportStoreContext($request);
        $storeIds = $storeCtx['store_ids'];
        $displayStoreId = $storeCtx['store_id'] ?? $storeIds[0];
        $tab = $this->bookingFunnelReportService->resolveTab((string) $request->query('tab', BookingFunnelReportService::TAB_SMS_PENDING));
        $rows = $this->bookingFunnelReportService->paginatedTabQuery($tab, $storeIds);
        $authCodeClients = in_array($tab, [
            BookingFunnelReportService::TAB_SMS_PENDING,
            BookingFunnelReportService::TAB_OTP_FAILED,
        ], true)
            ? $this->bookingFunnelReportService->clientsForAuthCodes($rows->getCollection(), $storeIds)
            : [];

        return view('relatorios.booking-funnel', [
            'pageTitle' => 'Relatórios — Funil Booking',
            'reportStoreFilter' => $storeCtx,
            'activeTab' => $tab,
            'summaryCounts' => $this->bookingFunnelReportService->summaryCounts($storeIds),
            'rows' => $rows,
            'authCodeClients' => $authCodeClients,
            'storeTimezone' => StoreBusinessTime::timezoneForStore($displayStoreId),
            'displayStoreId' => $displayStoreId,
            'funnelService' => $this->bookingFunnelReportService,
        ]);
    }

    public function sms(Request $request): View
    {
        $storeCtx = $this->resolveReportStoreContext($request);
        $storeIds = $storeCtx['store_ids'];
        $displayStoreId = $storeCtx['store_id'] ?? $storeIds[0];
        $today = StoreBusinessTime::nowForStore($displayStoreId)->startOfDay();
        $availableYears = $this->smsReportService->availableYears($storeIds);
        $year = (int) $request->get('year', $today->year);
        $year = max($availableYears[0] ?? $today->year, min($today->year, $year));
        $month = max(1, min(12, (int) $request->get('month', $today->month)));
        if ($year === $today->year && $month > $today->month) {
            $month = $today->month;
        }

        $messages = $this->smsReportService->reportQuery($storeIds, $year, $month)
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('relatorios.sms', [
            'pageTitle' => 'Relatórios — SMS',
            'reportStoreFilter' => $storeCtx,
            'messages' => $messages,
            'summaryCounts' => $this->smsReportService->summaryCounts($storeIds),
            'month' => $month,
            'year' => $year,
            'monthOptions' => $this->smsReportService->monthOptions(),
            'availableYears' => $availableYears,
            'periodLabel' => $this->smsReportService->periodLabel($year, $month, $displayStoreId),
            'typeLabels' => SmsMessage::typeLabels(),
            'storeTimezone' => StoreBusinessTime::timezoneForStore($displayStoreId),
        ]);
    }

    /**
     * @return array{store_id: ?int, scope: 'loja'|'todas', store_ids: list<int>}
     */
    private function resolveReportStoreContext(Request $request): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $filter = StoreContextPreference::resolveFilter($user, $request, true);
        if ($filter['store_id'] !== null) {
            StoreContextPreference::persist($request, $filter['store_id']);
        } else {
            StoreContextPreference::persistAll($request);
        }

        return $filter;
    }

    /**
     * @param  array{store_id: ?int, scope: string, store_ids: list<int>}  $storeCtx
     */
    private function relatorioLojaFiltroLine(array $storeCtx): string
    {
        if (($storeCtx['scope'] ?? '') === StoreContextPreference::SCOPE_ALL) {
            return 'Loja: Todas as lojas';
        }

        $storeId = $storeCtx['store_id'] ?? ($storeCtx['store_ids'][0] ?? null);
        $name = $storeId !== null
            ? Store::query()->whereKey($storeId)->value('name')
            : current_store()->tryGet()?->name;

        return 'Loja: '.($name !== null && $name !== '' ? $name : '—');
    }
}
