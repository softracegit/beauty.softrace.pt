<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use App\Support\VendasReportPdfColumns;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class VendasReportRunService
{
    public function __construct(
        private readonly VendasReportService $vendasReportService,
    ) {}

    /**
     * @param  array{desde?: ?string, ate?: ?string, cliente?: mixed, servico?: mixed, tecnico?: mixed, estado?: ?string, data_criterio?: ?string}  $filters
     * @return array{
     *   sales: Collection<int, Sale>,
     *   lines: Collection<int, object>,
     *   dateCriterion: string,
     *   totais: array<string, float|int>,
     *   filtrosLinhas: array<int, string>
     * }
     */
    public function build(array $filters): array
    {
        $dateCriterion = VendasReportService::resolveDateCriterion($filters['data_criterio'] ?? null);
        $sales = $this->salesForReport($filters, $dateCriterion);
        $lines = $this->vendasReportService->resumoCollection(
            $sales,
            isset($filters['servico']) ? (string) $filters['servico'] : null,
            isset($filters['tecnico']) ? (string) $filters['tecnico'] : null,
            $dateCriterion,
        );

        return [
            'sales' => $sales,
            'lines' => $lines,
            'dateCriterion' => $dateCriterion,
            'totais' => $this->vendasReportService->totaisRodape($lines, $dateCriterion, $sales),
            'filtrosLinhas' => $this->filtrosResumo($filters, $dateCriterion),
        ];
    }

    /**
     * @param  array{desde?: ?string, ate?: ?string, cliente?: mixed, servico?: mixed, tecnico?: mixed, estado?: ?string, data_criterio?: ?string}  $filters
     */
    public function streamPdf(array $filters, ?Request $request = null): Response
    {
        $built = $this->build($filters);
        $dateCriterion = $built['dateCriterion'];
        $pdfColumnLabels = $this->pdfColumnLabels($dateCriterion);
        $pdfColumns = $request instanceof Request
            ? VendasReportPdfColumns::resolveFromRequest($request)
            : array_keys(VendasReportPdfColumns::LABELS);
        $orientation = $request instanceof Request
            ? VendasReportPdfColumns::resolveOrientationFromRequest($request)
            : 'landscape';

        $pdf = Pdf::loadView('relatorios.pdf.vendas', [
            'linhas' => $built['lines'],
            'filtrosLinhas' => $built['filtrosLinhas'],
            'appName' => config('app.name'),
            'totalLinhas' => $built['lines']->count(),
            'vendasTotais' => $built['totais'],
            'vendasDataColunaLabel' => $this->dataColunaLabel($dateCriterion),
            'pdfColumns' => $pdfColumns,
            'pdfColumnLabels' => $pdfColumnLabels,
        ])->setPaper('a4', $orientation);

        $filename = 'vendas_'.now()->format('Y-m-d_His').'.pdf';

        return $pdf->stream($filename);
    }

    /**
     * @param  array{desde?: ?string, ate?: ?string, cliente?: mixed, servico?: mixed, tecnico?: mixed, estado?: ?string, data_criterio?: ?string}  $filters
     * @return array<string, string>
     */
    public function pdfQueryParameters(array $filters): array
    {
        $params = [
            'vendas_desde' => $filters['desde'] ?? null,
            'vendas_ate' => $filters['ate'] ?? null,
            'vendas_estado' => $filters['estado'] ?? null,
            'vendas_data_criterio' => VendasReportService::resolveDateCriterion($filters['data_criterio'] ?? null),
            'vendas_servico' => $filters['servico'] ?? null,
            'vendas_tecnico' => $filters['tecnico'] ?? null,
            'vendas_cliente' => $filters['cliente'] ?? null,
        ];

        return array_filter(
            $params,
            fn (mixed $value): bool => $value !== null && $value !== '',
        );
    }

    /**
     * @param  array{desde?: ?string, ate?: ?string, cliente?: mixed, servico?: mixed, tecnico?: mixed, estado?: ?string, data_criterio?: ?string}  $filters
     */
    public function filtersFromRequest(Request $request): array
    {
        return [
            'desde' => $request->get('vendas_desde'),
            'ate' => $request->get('vendas_ate'),
            'cliente' => $request->get('vendas_cliente'),
            'servico' => $request->get('vendas_servico'),
            'tecnico' => $request->get('vendas_tecnico'),
            'estado' => $request->get('vendas_estado'),
            'data_criterio' => $request->get('vendas_data_criterio'),
        ];
    }

    /**
     * @param  array{desde?: ?string, ate?: ?string, cliente?: mixed, servico?: mixed, tecnico?: mixed, estado?: ?string, data_criterio?: ?string}  $filters
     * @return Collection<int, Sale>
     */
    private function salesForReport(array $filters, string $dateCriterion): Collection
    {
        $sales = $this->vendasReportService->reportQuery($filters)
            ->with(['client', 'calendarEvent.user', 'calendarEvent.eventServiceItems.extras.extra', 'settledEvents', 'items.service.category', 'items.extra', 'items.calendarEventService.service.category', 'items.calendarEventService.event.user'])
            ->get();

        if ($dateCriterion === VendasReportService::DATE_CRITERION_MARCACAO) {
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

    /**
     * @param  array{desde?: ?string, ate?: ?string, cliente?: mixed, servico?: mixed, tecnico?: mixed, estado?: ?string, data_criterio?: ?string}  $filters
     * @return array<int, string>
     */
    private function filtrosResumo(array $filters, string $dateCriterion): array
    {
        $desde = $filters['desde'] ?? now()->copy()->startOfMonth()->toDateString();
        $ate = $filters['ate'] ?? now()->copy()->endOfMonth()->toDateString();

        $lines = [
            'Período ('.mb_strtolower(VendasReportService::dateCriterionLabel($dateCriterion)).'): '
                .Carbon::parse($desde)->format('d/m/Y').' a '.Carbon::parse($ate)->format('d/m/Y'),
        ];

        if ($dateCriterion === VendasReportService::DATE_CRITERION_MARCACAO) {
            $lines[] = 'Marcações: apenas pagas (completo)';
        }

        if ($cid = $filters['cliente'] ?? null) {
            $lines[] = 'Cliente: '.(Client::query()->forStore(current_store_id())->find($cid)?->name ?? '—');
        }
        if ($sid = $filters['servico'] ?? null) {
            $lines[] = 'Serviço: '.(Service::query()->forStore(current_store_id())->find($sid)?->name ?? '—');
        }
        if ($tid = $filters['tecnico'] ?? null) {
            $lines[] = 'Técnico: '.(User::activeStaff(current_store_id())->find($tid)?->name ?? '—');
        }
        if ($est = $filters['estado'] ?? null) {
            $label = $est === Sale::INVOICE_STATUS_RASCUNHO ? 'Rascunho' : ($est === Sale::INVOICE_STATUS_FATURADO ? 'Faturado' : $est);
            $lines[] = 'Estado da fatura: '.$label;
        } else {
            $lines[] = 'Estado da fatura: Faturado e Rascunho';
        }

        return $lines;
    }

    /**
     * @return array<string, string>
     */
    private function pdfColumnLabels(string $dateCriterion): array
    {
        $labels = VendasReportPdfColumns::labels();
        $labels['data'] = $this->dataColunaLabel($dateCriterion);

        return $labels;
    }

    private function dataColunaLabel(string $dateCriterion): string
    {
        return $dateCriterion === VendasReportService::DATE_CRITERION_MARCACAO
            ? 'Data marcação'
            : 'Data emissão';
    }
}
