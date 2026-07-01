<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientTag;
use App\Support\ClientTagStyle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class ClientTagService
{
    public const MAX_TAGS_PER_CLIENT = 5;

    public function findOrCreateForStore(string $name, ?int $storeId = null): ClientTag
    {
        $storeId ??= current_store_id();
        $normalized = $this->normalizeName($name);

        if ($normalized === '') {
            throw ValidationException::withMessages([
                'name' => 'Indique o nome da etiqueta.',
            ]);
        }

        $existing = ClientTag::query()
            ->forStore($storeId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($normalized, 'UTF-8')])
            ->first();

        if ($existing) {
            return $existing;
        }

        $maxOrder = ClientTag::query()->forStore($storeId)->max('sort_order') ?? 0;

        return ClientTag::create([
            'store_id' => $storeId,
            'name' => $normalized,
            'color' => ClientTagStyle::defaultColor(),
            'sort_order' => $maxOrder + 1,
        ]);
    }

    /**
     * @param  array<int|string>  $tagIds
     * @param  array<int, string>  $newTagNames
     * @return Collection<int, ClientTag>
     */
    public function syncClientTags(Client $client, array $tagIds, array $newTagNames = []): Collection
    {
        $storeId = (int) $client->store_id;
        $resolvedIds = [];

        foreach ($tagIds as $tagId) {
            $id = (int) $tagId;
            if ($id <= 0) {
                continue;
            }
            $exists = ClientTag::query()->forStore($storeId)->whereKey($id)->exists();
            if (! $exists) {
                throw ValidationException::withMessages([
                    'tag_ids' => 'Etiqueta inválida.',
                ]);
            }
            $resolvedIds[] = $id;
        }

        foreach ($newTagNames as $newName) {
            if (! is_string($newName)) {
                continue;
            }
            $tag = $this->findOrCreateForStore($newName, $storeId);
            $resolvedIds[] = (int) $tag->id;
        }

        $resolvedIds = array_values(array_unique($resolvedIds));

        if (count($resolvedIds) > self::MAX_TAGS_PER_CLIENT) {
            throw ValidationException::withMessages([
                'tag_ids' => 'Máximo de '.self::MAX_TAGS_PER_CLIENT.' etiquetas por cliente.',
            ]);
        }

        $client->tags()->sync($resolvedIds);

        $client->load('tags');

        return $client->tags;
    }

    public function deleteTag(ClientTag $tag): void
    {
        if ($tag->clients()->exists()) {
            throw ValidationException::withMessages([
                'tag' => 'Não é possível eliminar uma etiqueta associada a clientes.',
            ]);
        }

        $tag->delete();
    }

    public function normalizeName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        return mb_substr($name, 0, 80, 'UTF-8');
    }

    /**
     * @return Collection<int, ClientTag>
     */
    public function tagsForStore(?int $storeId = null): Collection
    {
        $storeId ??= current_store_id();

        return ClientTag::query()
            ->forStore($storeId)
            ->withCount('clients')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
