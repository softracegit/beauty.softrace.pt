<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LlmClient
{
    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return array<string, mixed>
     */
    public function chat(array $messages, array $tools = []): array
    {
        $apiKey = config('ai_assistant.api_key');
        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException('O assistente AI não está configurado. Defina AI_ASSISTANT_API_KEY no .env.');
        }

        $payload = [
            'model' => config('ai_assistant.model'),
            'messages' => $messages,
            'temperature' => 0.2,
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout((int) config('ai_assistant.timeout_seconds', 60))
                ->acceptJson()
                ->post(config('ai_assistant.base_url').'/chat/completions', $payload);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Não foi possível contactar o serviço de AI: '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            $body = $response->json();
            $message = is_array($body) ? (string) ($body['error']['message'] ?? $response->body()) : $response->body();
            throw new RuntimeException('Erro do serviço de AI: '.$message);
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException('Resposta inválida do serviço de AI.');
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array{role: string, content: ?string, tool_calls: array<int, array<string, mixed>>}
     */
    public function extractAssistantMessage(array $response): array
    {
        $message = $response['choices'][0]['message'] ?? null;
        if (! is_array($message)) {
            throw new RuntimeException('Resposta do assistente sem conteúdo.');
        }

        return [
            'role' => (string) ($message['role'] ?? 'assistant'),
            'content' => isset($message['content']) ? (string) $message['content'] : null,
            'tool_calls' => is_array($message['tool_calls'] ?? null) ? $message['tool_calls'] : [],
        ];
    }
}
