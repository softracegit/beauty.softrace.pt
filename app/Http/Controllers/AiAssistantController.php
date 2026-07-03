<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Ai\AiAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class AiAssistantController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureCanAccessAi($request);

        return view('ai.index', [
            'pageTitle' => 'Assistente AI',
            'aiConfigured' => $this->isConfigured(),
        ]);
    }

    public function chat(Request $request, AiAssistantService $assistant): JsonResponse
    {
        $this->ensureCanAccessAi($request);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'messages' => ['nullable', 'array', 'max:20'],
            'messages.*.role' => ['required_with:messages', 'in:user,assistant'],
            'messages.*.content' => ['required_with:messages', 'string', 'max:8000'],
        ]);

        try {
            $result = $assistant->chat(
                $validated['message'],
                $validated['messages'] ?? [],
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'error' => 'Ocorreu um erro ao processar o pedido. Tente novamente.',
            ], 500);
        }

        return response()->json([
            'reply' => $result['reply'],
            'tool' => $result['tool'],
            'data' => $result['data'],
        ]);
    }

    private function isConfigured(): bool
    {
        if (! config('ai_assistant.enabled', true)) {
            return false;
        }

        $key = config('ai_assistant.api_key');

        return is_string($key) && trim($key) !== '';
    }

    private function ensureCanAccessAi(Request $request): void
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->canAccessAi()) {
            abort(403, 'Sem permissão para aceder ao assistente AI.');
        }
    }
}
