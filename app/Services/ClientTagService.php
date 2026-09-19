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

    public function findOrCreateForOrganization(string $name, ?int $organizationId = null): ClientTag
    {
        $organizationId ??= current_organization_id();
        $normalized = $this->normalizeName($name);

        if ($normalized === '') {
            throw ValidationException::withMessages([
                'name' => 'Indique o nome da etiqueta.',
            ]);
        }

        $existing = ClientTag::query()
            ->forOrganization($organizationId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($normalized, 'UTF-8')])
            ->first();

        if ($existing) {
            return $existing;
        }

        $maxOrder = ClientTag::query()->forOrganization($organizationId)->max('sort_order') ?? 0;

        return ClientTag::create([
            'organization_id' => $organizationId,
            'store_id' => current_store_id(),
            'name' => $normalized,
            'color' => ClientTagStyle::defaultColor(),
            'sort_order' => $maxOrder + 1,
        ]);
    }

    /** @deprecated Use findOrCreateForOrganization */
    public function findOrCreateForStore(string $name, ?int $storeId = null): ClientTag
    {
        $orgId = $storeId
            ? (int) \App\Models\Store::query()->whereKey($storeId)->value('organization_id')
            : current_organization_id();

        return $this->findOrCreateForOrganization($name, $orgId);
    }

    /**
     * @param  array<int|string>  $tagIds
     * @param  array<int, string>  $newTagNames
     * @return Collection<int, ClientTag>
     */
    public function syncClientTags(Client $client, array $tagIds, array $newTagNames = []): Collection
    {
        $organizationId = (int) $client->organization_id;
        $resolvedIds = [];

        foreach ($tagIds as $tagId) {
            $id = (int) $tagId;
            if ($id <= 0) {
                continue;
            }
            $exists = ClientTag::query()->forOrganization($organizationId)->whereKey($id)->exists();
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
            $tag = $this->findOrCreateForOrganization($newName, $organizationId);
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
    public function tagsForOrganization(?int $organizationId = null): Collection
    {
        $organizationId ??= current_organization_id();

        return ClientTag::query()
            ->forOrganization($organizationId)
            ->withCount('clients')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /** @deprecated Use tagsForOrganization */
    public function tagsForStore(?int $storeId = null): Collection
    {
        return $this->tagsForOrganization(
            $storeId
                ? (int) \App\Models\Store::query()->whereKey($storeId)->value('organization_id')
                : null
        );
    }
}
