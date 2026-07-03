<?php

namespace App\Services\Ai\Tools;

use App\Models\Sale;
use App\Models\User;
use App\Services\VendasReportRunService;
use App\Services\VendasReportService;
use App\Support\AiReportPeriodResolver;
use Illuminate\Support\Str;

class RelatorioVendasPdfTool
{
    public function __construct(
        private readonly VendasReportRunService $vendasReportRun,
    ) {}

    public static function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'relatorio_vendas_pdf',
                'description' => 'Gera relatório de vendas em PDF para um período. Use para pedidos de relatório/exportação/PDF de vendas, faturação ou receitas.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'periodo' => [
                            'type' => 'string',
                            'enum' => ['mes_passado', 'mes_atual', 'ultimos_6_meses'],
                            'description' => 'Período relativo. Por omissão: mes_passado.',
                        ],
                        'desde' => [
                            'type' => 'string',
                            'description' => 'Data inicial YYYY-MM-DD (alternativa a periodo).',
                        ],
                        'ate' => [
                            'type' => 'string',
                            'description' => 'Data final YYYY-MM-DD (alternativa a periodo).',
                        ],
                        'estado_fatura' => [
                            'type' => 'string',
                            'enum' => ['todos', 'rascunho', 'faturado'],
                            'description' => 'Filtrar por estado da fatura.',
                        ],
                        'criterio_data' => [
                            'type' => 'string',
                            'enum' => ['marcacao', 'emissao'],
                            'description' => 'Data da marcação (pago) ou data da fatura.',
                        ],
                        'tecnico_nome' => [
                            'type' => 'string',
                            'description' => 'Nome (parcial) do técnico/colaborador.',
                        ],
                    ],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(array $arguments = []): array
    {
        $storeId = current_store_id();
        $period = AiReportPeriodResolver::resolve(
            $storeId,
            isset($arguments['periodo']) ? (string) $arguments['periodo'] : null,
            isset($arguments['desde']) ? (string) $arguments['desde'] : null,
            isset($arguments['ate']) ? (string) $arguments['ate'] : null,
        );

        $filters = [
            'desde' => $period['desde'],
            'ate' => $period['ate'],
            'estado' => $this->resolveEstado($arguments['estado_fatura'] ?? null),
            'data_criterio' => $this->resolveCriterioData($arguments['criterio_data'] ?? null),
            'tecnico' => $this->resolveTecnicoId($arguments['tecnico_nome'] ?? null),
        ];

        $built = $this->vendasReportRun->build($filters);
        $query = $this->vendasReportRun->pdfQueryParameters($filters);

        return [
            'summary' => $this->buildSummary($period['label'], $built['lines']->count(), $built['totais']),
            'period_label' => $period['label'],
            'desde' => $period['desde'],
            'ate' => $period['ate'],
            'total_linhas' => $built['lines']->count(),
            'total_absoluto' => round((float) ($built['totais']['total_absoluto'] ?? 0), 2),
            'num_vendas' => (int) ($built['totais']['num_vendas'] ?? 0),
            'download_url' => route('relatorios.vendas.pdf', $query),
            'relatorios_url' => route('relatorios.vendas', $query),
        ];
    }

    private function resolveEstado(mixed $value): ?string
    {
        $value = is_string($value) ? mb_strtolower(trim($value), 'UTF-8') : '';

        return match ($value) {
            'rascunho' => Sale::INVOICE_STATUS_RASCUNHO,
            'faturado' => Sale::INVOICE_STATUS_FATURADO,
            default => null,
        };
    }

    private function resolveCriterioData(mixed $value): string
    {
        $value = is_string($value) ? mb_strtolower(trim($value), 'UTF-8') : '';

        if (in_array($value, ['emissao', 'fatura', 'faturacao'], true)) {
            return VendasReportService::DATE_CRITERION_EMISSAO;
        }

        return VendasReportService::DATE_CRITERION_MARCACAO;
    }

    private function resolveTecnicoId(mixed $name): ?int
    {
        if (! is_string($name) || trim($name) === '') {
            return null;
        }

        $needle = Str::ascii(mb_strtolower(trim($name), 'UTF-8'));

        $match = User::activeServiceProviders(current_store_id())
            ->get(['id', 'name'])
            ->first(function (User $user) use ($needle): bool {
                $haystack = Str::ascii(mb_strtolower(trim($user->name), 'UTF-8'));

                return str_contains($haystack, $needle);
            });

        return $match ? (int) $match->id : null;
    }

    /**
     * @param  array<string, float|int>  $totais
     */
    private function buildSummary(string $periodLabel, int $lineCount, array $totais): string
    {
        if ($lineCount === 0) {
            return 'Não há vendas no período '.$periodLabel.'.';
        }

        return sprintf(
            'Relatório de vendas (%s): %d linha(s), %d venda(s), total %.2f €.',
            $periodLabel,
            $lineCount,
            (int) ($totais['num_vendas'] ?? 0),
            (float) ($totais['total_absoluto'] ?? 0),
        );
    }
}
