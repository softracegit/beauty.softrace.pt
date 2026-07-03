<?php

namespace App\Services\Ai;

use App\Services\Ai\Tools\AuditoriaClientesDuplicadosTool;
use App\Services\Ai\Tools\RelatorioVendasPdfTool;
use RuntimeException;

class AiAssistantService
{
    public function __construct(
        private readonly LlmClient $llm,
        private readonly AuditoriaClientesDuplicadosTool $duplicadosTool,
        private readonly RelatorioVendasPdfTool $vendasPdfTool,
    ) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{reply: string, tool: ?string, data: ?array<string, mixed>}
     */
    public function chat(string $message, array $history = []): array
    {
        if (! config('ai_assistant.enabled', true)) {
            throw new RuntimeException('O assistente AI está desactivado.');
        }

        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt()],
        ];

        $maxHistory = (int) config('ai_assistant.max_history_messages', 12);
        foreach (array_slice($history, -$maxHistory) as $item) {
            $role = $item['role'] ?? '';
            $content = trim((string) ($item['content'] ?? ''));
            if (! in_array($role, ['user', 'assistant'], true) || $content === '') {
                continue;
            }
            $messages[] = ['role' => $role, 'content' => $content];
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        $tools = [
            AuditoriaClientesDuplicadosTool::definition(),
            RelatorioVendasPdfTool::definition(),
        ];

        $first = $this->llm->chat($messages, $tools);
        $assistant = $this->llm->extractAssistantMessage($first);

        if ($assistant['tool_calls'] === []) {
            return [
                'reply' => trim((string) ($assistant['content'] ?? '')) ?: 'Não consegui gerar uma resposta.',
                'tool' => null,
                'data' => null,
            ];
        }

        $toolCall = $assistant['tool_calls'][0];
        $toolName = (string) ($toolCall['function']['name'] ?? '');
        $arguments = $this->decodeToolArguments($toolCall['function']['arguments'] ?? '{}');

        $toolResult = $this->executeTool($toolName, $arguments);

        $messages[] = [
            'role' => 'assistant',
            'content' => $assistant['content'],
            'tool_calls' => $assistant['tool_calls'],
        ];
        $messages[] = [
            'role' => 'tool',
            'tool_call_id' => (string) ($toolCall['id'] ?? 'tool_call'),
            'content' => json_encode($toolResult, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];

        $second = $this->llm->chat($messages);
        $finalAssistant = $this->llm->extractAssistantMessage($second);

        return [
            'reply' => trim((string) ($finalAssistant['content'] ?? '')) ?: ($toolResult['summary'] ?? 'Concluído.'),
            'tool' => $toolName,
            'data' => $toolResult,
        ];
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
És o assistente de dados do CRM de um salão/estética. Respondes sempre em português de Portugal.

Regras:
- Usa as ferramentas disponíveis para obter dados reais; não inventes números nem listas.
- Quando o utilizador pedir clientes duplicados, telefones parecidos, erros Zappy ou fusão de fichas, chama auditoria_clientes_duplicados.
- Quando pedir relatório/PDF/exportação de vendas, receitas ou faturação, chama relatorio_vendas_pdf (ex.: «vendas do mês passado em PDF», «rascunhos dos últimos 6 meses»).
- Se o pedido for vago, faz uma pergunta curta de clarificação.
- Sê conciso. Resume os resultados e destaca os casos mais prováveis.
- Não executes acções destrutivas (apagar, fundir) — apenas análise e recomendações.
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeToolArguments(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        $json = is_string($raw) ? $raw : '{}';
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function executeTool(string $toolName, array $arguments): array
    {
        return match ($toolName) {
            'auditoria_clientes_duplicados' => $this->duplicadosTool->execute($arguments),
            'relatorio_vendas_pdf' => $this->vendasPdfTool->execute($arguments),
            default => throw new RuntimeException('Ferramenta desconhecida: '.$toolName),
        };
    }
}
