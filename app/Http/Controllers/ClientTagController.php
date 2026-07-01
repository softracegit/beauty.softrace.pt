<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientTag;
use App\Models\User;
use App\Services\ClientTagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClientTagController extends Controller
{
    public function __construct(
        private readonly ClientTagService $tagService,
    ) {}

    public function index(): JsonResponse
    {
        $tags = $this->tagService->tagsForStore();

        return response()->json([
            'tags' => $tags->map(fn (ClientTag $tag) => [
                ...$tag->toPickerArray(),
                'clients_count' => (int) ($tag->clients_count ?? 0),
            ])->values()->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertCanManageTags();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ], [
            'name.required' => 'Indique o nome da etiqueta.',
        ]);

        $tag = $this->tagService->findOrCreateForStore(
            $validated['name'],
            current_store_id(),
        );

        return response()->json([
            'success' => true,
            'tag' => [
                ...$tag->fresh()->toPickerArray(),
                'clients_count' => 0,
            ],
        ], 201);
    }

    public function update(Request $request, ClientTag $clientTag): JsonResponse
    {
        $this->assertCanManageTags();

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:80'],
        ]);

        if (array_key_exists('name', $validated)) {
            $normalized = $this->tagService->normalizeName($validated['name']);
            if ($normalized === '') {
                throw ValidationException::withMessages([
                    'name' => 'Indique o nome da etiqueta.',
                ]);
            }

            $duplicate = ClientTag::query()
                ->forStore(current_store_id())
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($normalized, 'UTF-8')])
                ->whereKeyNot($clientTag->id)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'name' => 'Já existe uma etiqueta com este nome.',
                ]);
            }

            $clientTag->name = $normalized;
        }

        $clientTag->save();

        return response()->json([
            'success' => true,
            'tag' => [
                ...$clientTag->fresh()->toPickerArray(),
                'clients_count' => $clientTag->clients()->count(),
            ],
        ]);
    }

    public function destroy(ClientTag $clientTag): JsonResponse
    {
        $this->assertCanManageTags();

        $this->tagService->deleteTag($clientTag);

        return response()->json(['success' => true]);
    }

    public function syncForClient(Request $request, Client $client): JsonResponse
    {
        $this->assertCanEditClientTags();

        if ((int) $client->store_id !== (int) current_store_id()) {
            abort(404);
        }

        $validated = $request->validate([
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer'],
            'new_tag_names' => ['nullable', 'array'],
            'new_tag_names.*' => ['string', 'max:80'],
        ]);

        $tags = $this->tagService->syncClientTags(
            $client,
            $validated['tag_ids'] ?? [],
            $validated['new_tag_names'] ?? [],
        );

        return response()->json([
            'success' => true,
            'tags' => $tags->map(fn (ClientTag $tag) => $tag->toPickerArray())->values()->all(),
        ]);
    }

    private function assertCanManageTags(): void
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $user->canAccessDefinicoes()) {
            abort(403, 'Sem permissão.');
        }
    }

    private function assertCanEditClientTags(): void
    {
        $user = auth()->user();
        if (! $user instanceof User || $user->isPrestador()) {
            abort(403, 'Sem permissão.');
        }
    }
}
