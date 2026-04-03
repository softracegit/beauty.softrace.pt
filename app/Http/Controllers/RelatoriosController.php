<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
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
            ->join('calendar_event_services', 'services.id', '=', 'calendar_event_services.service_id')
            ->join('calendar_events', 'calendar_events.id', '=', 'calendar_event_services.calendar_event_id')
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->select('services.id', 'services.name')
            ->distinct()
            ->orderBy('services.name')
            ->get();

        $tecnicosOpts = User::query()
            ->join('calendar_events', 'calendar_events.user_id', '=', 'users.id')
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->select('users.id', 'users.name')
            ->distinct()
            ->orderBy('users.name')
            ->get();

        $clientesOpts = Client::query()
            ->join('calendar_events', 'calendar_events.client_id', '=', 'clients.id')
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
                ->map(fn ($es) => $es->service?->name)
                ->filter()
                ->implode(', ');
            $statusLabel = CalendarEvent::statuses()[$ev->status] ?? $ev->status;

            $sheet->fromArray([
                [
                    $ev->start_at->format('d/m/Y H:i'),
                    $statusLabel,
                    $ev->client?->name ?? '',
                    $ev->user?->name ?? '',
                    $services,
                    round($totalPreco, 2),
                    $ev->description ?? '',
                ],
            ], null, 'A'.$rowIndex);
            $rowIndex++;
        }

        foreach (range('A', 'G') as $col) {
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
            ->with(['user', 'client', 'eventServiceItems.service', 'eventServiceItems.extras'])
            ->orderByDesc('start_at')
            ->get();

        $pdf = Pdf::loadView('relatorios.pdf.marcacoes', [
            'marcacoes' => $events,
            'filtrosLinhas' => $this->marcacoesFiltrosResumo($request),
            'appName' => config('app.name'),
            'totalRegistos' => $events->count(),
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
            $lines[] = 'Cliente: '.(Client::query()->find($cid)?->name ?? '—');
        }
        if ($sid = $request->get('marcacoes_servico')) {
            $lines[] = 'Serviço: '.(Service::query()->find($sid)?->name ?? '—');
        }
        if ($tid = $request->get('marcacoes_tecnico')) {
            $lines[] = 'Técnico: '.(User::query()->find($tid)?->name ?? '—');
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

    public function vendas(Request $request): View
    {
        $sales = $this->vendasReportQuery($request)
            ->with(['client', 'calendarEvent.user', 'items.service', 'items.extra'])
            ->orderByDesc('data_emissao')
            ->orderByDesc('id')
            ->get();

        $allLines = $this->vendasLinhasCollection($sales, $request->get('vendas_servico'));

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
            'tecnicosOpts' => $this->vendasTecnicosOpts(),
        ]);
    }

    public function vendasExport(Request $request): StreamedResponse
    {
        $sales = $this->vendasReportQuery($request)
            ->with(['client', 'calendarEvent.user', 'items.service', 'items.extra'])
            ->orderByDesc('data_emissao')
            ->orderByDesc('id')
            ->get();

        $lines = $this->vendasLinhasCollection($sales, $request->get('vendas_servico'));

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
            'Qtd',
            'Valor (€)',
            'Estado venda',
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
                    $linha->quantidade,
                    round($linha->valor, 2),
                    Sale::statuses()[$linha->sale_status] ?? $linha->sale_status,
                ],
            ], null, 'A'.$rowIndex);
            $rowIndex++;
        }

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
            ->with(['client', 'calendarEvent.user', 'items.service', 'items.extra'])
            ->orderByDesc('data_emissao')
            ->orderByDesc('id')
            ->get();

        $lines = $this->vendasLinhasCollection($sales, $request->get('vendas_servico'));

        $pdf = Pdf::loadView('relatorios.pdf.vendas', [
            'linhas' => $lines,
            'filtrosLinhas' => $this->vendasFiltrosResumo($request),
            'appName' => config('app.name'),
            'totalLinhas' => $lines->count(),
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
            $lines[] = 'Cliente: '.(Client::query()->find($cid)?->name ?? '—');
        }
        if ($sid = $request->get('vendas_servico')) {
            $lines[] = 'Serviço: '.(Service::query()->find($sid)?->name ?? '—');
        }
        if ($tid = $request->get('vendas_tecnico')) {
            $lines[] = 'Técnico: '.(User::query()->find($tid)?->name ?? '—');
        }
        if ($est = $request->get('vendas_estado')) {
            $lines[] = 'Estado da venda: '.(Sale::statuses()[$est] ?? $est);
        } else {
            $lines[] = 'Estado da venda: Pago e Anulado';
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
        $estado = $request->get('vendas_estado');

        if (! $desde) {
            $desde = $firstDayOfMonth->toDateString();
        }
        if (! $ate) {
            $ate = $today->toDateString();
        }

        $q = Sale::query()
            ->whereHas('calendarEvent', function (Builder $cq) {
                $cq->where('event_type', CalendarEvent::TYPE_MARCACAO)
                    ->where('status', '!=', CalendarEvent::STATUS_CANCELADO);
            });

        if ($estado) {
            $q->where('status', $estado);
        } else {
            $q->whereIn('status', [Sale::STATUS_PAGO, Sale::STATUS_ANULADO]);
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
            $q->whereHas('calendarEvent', fn (Builder $cq) => $cq->where('user_id', $tecnico));
        }

        return $q;
    }

    /**
     * Uma linha por item de venda (serviço ou extra), alinhado à ficha cliente — Vendas.
     *
     * @return Collection<int, object>
     */
    private function vendasLinhasCollection(Collection $sales, ?string $vendasServico): Collection
    {
        $servicoFilter = $vendasServico !== null && $vendasServico !== '' ? (int) $vendasServico : null;

        return $sales->flatMap(function (Sale $sale) use ($servicoFilter) {
            $filteredCesIds = null;
            if ($servicoFilter) {
                $filteredCesIds = $sale->items
                    ->where('tipo', SaleItem::TIPO_SERVICO)
                    ->where('service_id', $servicoFilter)
                    ->pluck('calendar_event_service_id')
                    ->filter()
                    ->all();
            }

            $event = $sale->calendarEvent;
            $client = $sale->client;
            $tecName = $event?->user?->name ?? '—';
            $dataEmissao = $sale->data_emissao;

            $out = [];
            foreach ($sale->items as $item) {
                if ($servicoFilter) {
                    if ($item->tipo === SaleItem::TIPO_SERVICO && (int) $item->service_id !== $servicoFilter) {
                        continue;
                    }
                    if ($item->tipo === SaleItem::TIPO_EXTRA && $filteredCesIds !== null
                        && ! in_array($item->calendar_event_service_id, $filteredCesIds, true)) {
                        continue;
                    }
                }

                $label = $item->descricao;
                if ($item->tipo === SaleItem::TIPO_SERVICO && $item->service) {
                    $label = $item->service->name;
                } elseif ($item->tipo === SaleItem::TIPO_EXTRA && $item->extra) {
                    $label = $item->extra->name;
                }

                $out[] = (object) [
                    'sale' => $sale,
                    'sale_id' => $sale->id,
                    'sale_status' => $sale->status,
                    'data' => $dataEmissao,
                    'numero_fatura' => $sale->numero_fatura,
                    'cliente' => $client?->name ?? '—',
                    'nif' => $client?->nif ?? '',
                    'tecnico' => $tecName,
                    'servico' => $label ?? '—',
                    'quantidade' => (int) $item->quantidade,
                    'valor' => (float) $item->subtotal,
                    'tipo_item' => $item->tipo,
                ];
            }

            return $out;
        })->values();
    }

    private function vendasClientesOpts(): Collection
    {
        return Client::query()
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('sales')
                    ->join('calendar_events', 'calendar_events.id', '=', 'sales.calendar_event_id')
                    ->whereColumn('sales.client_id', 'clients.id')
                    ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
                    ->where('calendar_events.status', '!=', CalendarEvent::STATUS_CANCELADO);
            })
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function vendasServicosOpts(): Collection
    {
        return Service::query()
            ->join('sale_items', 'services.id', '=', 'sale_items.service_id')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('calendar_events', 'sales.calendar_event_id', '=', 'calendar_events.id')
            ->where('sale_items.tipo', SaleItem::TIPO_SERVICO)
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('calendar_events.status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->select('services.id', 'services.name')
            ->distinct()
            ->orderBy('services.name')
            ->get();
    }

    private function vendasTecnicosOpts(): Collection
    {
        return User::query()
            ->join('calendar_events', 'calendar_events.user_id', '=', 'users.id')
            ->join('sales', 'sales.calendar_event_id', '=', 'calendar_events.id')
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('calendar_events.status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->select('users.id', 'users.name')
            ->distinct()
            ->orderBy('users.name')
            ->get();
    }

    public function comissoes(): View
    {
        return view('relatorios.comissoes', [
            'pageTitle' => 'Relatórios — Comissões',
        ]);
    }
}
