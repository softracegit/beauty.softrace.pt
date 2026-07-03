<?php

namespace App\Services\Ai\Tools;

use App\Services\ClientDuplicateAuditService;
use Illuminate\Support\Collection;

class AuditoriaClientesDuplicadosTool
{
    public function __construct(
        private readonly ClientDuplicateAuditService $duplicateAudit,
    ) {}

    public static function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'auditoria_clientes_duplicados',
                'description' => 'Identifica pares de clientes possivelmente duplicados na loja actual (mesmo nome com telemóvel igual ou muito parecido, ou um sem telemóvel). Útil para erros de importação Zappy.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'max_phone_distance' => [
                            'type' => 'integer',
                            'description' => 'Distância máxima de Levenshtein entre dígitos do telemóvel (1 = um dígito de diferença).',
                            'minimum' => 0,
                            'maximum' => 3,
                        ],
                        'include_missing_phone' => [
                            'type' => 'boolean',
                            'description' => 'Incluir pares em que um cliente não tem telemóvel.',
                        ],
                    ],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{summary: string, pairs: array<int, array<string, mixed>>, total: int}
     */
    public function execute(array $arguments = []): array
    {
        $maxDistance = max(0, min(3, (int) ($arguments['max_phone_distance'] ?? 1)));
        $includeMissing = array_key_exists('include_missing_phone', $arguments)
            ? (bool) $arguments['include_missing_phone']
            : true;

        $pairs = $this->duplicateAudit->findSuspects(
            current_store_id(),
            $maxDistance,
            $includeMissing,
        );

        return [
            'summary' => $this->buildSummary($pairs),
            'pairs' => $pairs->map(fn (object $pair) => [
                'client_a_id' => $pair->client_a_id,
                'client_b_id' => $pair->client_b_id,
                'name' => $pair->name,
                'client_a_phone' => $pair->client_a_phone,
                'client_b_phone' => $pair->client_b_phone,
                'phone_distance' => $pair->phone_distance,
                'reason' => $pair->reason,
                'confidence' => $pair->confidence,
                'client_a_appointments' => $pair->client_a_appointments,
                'client_b_appointments' => $pair->client_b_appointments,
                'client_a_sales' => $pair->client_a_sales,
                'client_b_sales' => $pair->client_b_sales,
                'from_zappy' => $pair->from_zappy,
            ])->values()->all(),
            'total' => $pairs->count(),
        ];
    }

    /**
     * @param  Collection<int, object>  $pairs
     */
    private function buildSummary(Collection $pairs): string
    {
        if ($pairs->isEmpty()) {
            return 'Não foram encontrados pares de clientes suspeitos com os critérios indicados.';
        }

        $alta = $pairs->where('confidence', 'alta')->count();
        $zappy = $pairs->where('from_zappy', true)->count();

        return sprintf(
            'Encontrados %d par(es) suspeito(s): %d com confiança alta, %d com ligação a import Zappy.',
            $pairs->count(),
            $alta,
            $zappy,
        );
    }
}
