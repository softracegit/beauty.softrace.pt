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
use App\Services\SmsReportService;
use App\Services\VendasReportService;
use App\Support\DateTimeDisplay;
use App\Support\MarcacoesReportEstadoFilter;
use App\Support\StoreBusinessTime;
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
            $services = MarcacoesReportEstadoFilter::eventRowServicesLabel($ev);
            if ($ev->event_type === CalendarEvent::TYPE_TEMPO_PESSOAL) {
                $categorias = '—';
            } else {
                $categorias = $ev->eventServiceItems
                    ->map(fn ($es) => $es->service?->category?->name)
                    ->map(fn ($n) => $n !== null && $n !== '' ? $n : '—')
                    ->implode(', ');
            }
            $statusLabel = MarcacoesReportEstadoFilter::eventRowStatusLabel($ev);

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
            ->with(['user', 'client', 'personalTimeType', 'eventServiceItems.service.category', 'eventServiceItems.extras'])
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
            ->with(['client', 'calendarEvent.user', 'calendarEvent.eventServiceItems.extras.extra', 'items.service', 'items.extra', 'items.calendarEventService.service', 'items.calendarEventService.event.user'])
            ->orderByDesc('data_emissao')
            ->orderByDesc('id')
            ->get();

        $allLines = $this->vendasResumoCollection($sales, $request->get('vendas_servico'), $request->get('vendas_tecnico'));

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
            'clientesOpts' => $this->vendasClientesOpts(),
            'servicosOpts' => $this->vendasServicosOpts(),
            'tecnicosOpts' => $this->membrosOptsForRelatorios(),
            'vendasTotais' => $this->vendasTotaisRodape($allLines),
        ]);
    }

    public function vendasExport(Request $request): StreamedResponse
    {
        $sales = $this->vendasReportQuery($request)
            ->with(['client', 'calendarEvent.user', 'calendarEvent.eventServiceItems.extras.extra', 'items.service', 'items.extra', 'items.calendarEventService.service', 'items.calendarEventService.event.user'])
            ->orderByDesc('data_emissao')
            ->orderByDesc('id')
            ->get();

        $lines = $this->vendasResumoCollection($sales, $request->get('vendas_servico'), $request->get('vendas_tecnico'));

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Vendas');

        $headers = [
            'Data emissão',
            'Cliente',
            'NIF',
            'Técnico',
            'Serviço',
            'Total (€)',
            'Taxas (€)',
            'Gorjeta (€)',
            'Estado fatura',
        ];
        $sheet->fromArray($headers, null, 'A1');

        $rowIndex = 2;
        foreach ($lines as $linha) {
            $sheet->fromArray([
                [
                    $linha->data->format('d/m/Y'),
                    $linha->cliente,
                    $linha->nif,
                    $linha->tecnico,
                    $linha->servico,
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

        $totais = $this->vendasTotaisRodape($lines);
        $sheet->fromArray([
            [
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
                'Total',
                round($totais['total_absoluto'], 2),
                '',
                '',
                '',
            ],
        ], null, 'A'.$rowIndex);

        foreach (range('A', 'I') as $col) {
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
            ->with(['client', 'calendarEvent.user', 'calendarEvent.eventServiceItems.extras.extra', 'items.service', 'items.extra', 'items.calendarEventService.service', 'items.calendarEventService.event.user'])
            ->orderByDesc('data_emissao')
            ->orderByDesc('id')
            ->get();

        $lines = $this->vendasResumoCollection($sales, $request->get('vendas_servico'), $request->get('vendas_tecnico'));

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
        $desde = $request->get('vendas_desde') ?: $this->vendasDefaultDesde();
        $ate = $request->get('vendas_ate') ?: $this->vendasDefaultAte();

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
        return $this->vendasReportService->reportQuery([
            'desde' => $request->get('vendas_desde'),
            'ate' => $request->get('vendas_ate'),
            'cliente' => $request->get('vendas_cliente'),
            'servico' => $request->get('vendas_servico'),
            'tecnico' => $request->get('vendas_tecnico'),
            'estado' => $request->get('vendas_estado'),
        ]);
    }

    /**
     * @param  Collection<int, object>  $lines
     * @return array{total_valor: float, total_valor_com_gorjeta: float, total_gorjeta: float, total_taxas: float, total_absoluto: float, num_vendas: int, total_servicos: int, total_desconto: float, total_divida: float}
     */
    private function vendasTotaisRodape(Collection $lines): array
    {
        return $this->vendasReportService->totaisRodape($lines);
    }

    /**
     * @return Collection<int, object>
     */
    private function vendasResumoCollection(Collection $sales, ?string $vendasServico, ?string $vendasTecnico = null): Collection
    {
        return $this->vendasReportService->resumoCollection($sales, $vendasServico, $vendasTecnico);
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
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function comissoes(): View
    {
        return view('relatorios.comissoes', [
            'pageTitle' => 'Relatórios — Comissões',
        ]);
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
