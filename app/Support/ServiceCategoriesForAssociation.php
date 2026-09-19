<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Service;
use App\Models\Store;
use Illuminate\Support\Collection;

class ServiceCategoriesForAssociation
{
    public const UNCATEGORIZED_CATEGORY_KEY = -1;

    /**
     * Categorias da organização com serviços agrupados (cada serviço aparece uma única vez).
     *
     * @return array{categories: Collection<int, Category>, serviceCount: int}
     */
    public static function forOrganization(?int $organizationId = null): array
    {
        $organizationId = $organizationId ?? (int) current_organization_id();

        $categories = Category::forOrganization($organizationId)
            ->orderBy('sort_order')
            ->get();

        $allServices = Service::forOrganization($organizationId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $placedIds = [];

        foreach ($categories as $category) {
            $catServices = $allServices
                ->filter(fn (Service $service) => (int) $service->category_id === (int) $category->id)
                ->values();
            $placedIds = array_merge($placedIds, $catServices->pluck('id')->all());
            $category->setRelation('services', $catServices);
        }

        $orphans = $allServices
            ->filter(fn (Service $service) => ! in_array($service->id, $placedIds, true))
            ->values();

        if ($orphans->isNotEmpty()) {
            $uncategorized = new Category([
                'organization_id' => $organizationId,
                'name' => 'Sem categoria',
                'color' => '#6c757d',
                'sort_order' => 999999,
            ]);
            $uncategorized->id = self::UNCATEGORIZED_CATEGORY_KEY;
            $uncategorized->exists = true;
            $uncategorized->setRelation('services', $orphans);
            $categories->push($uncategorized);
        }

        return [
            'categories' => $categories,
            'serviceCount' => $allServices->count(),
        ];
    }

    /**
     * Compat: resolve organização a partir da loja.
     *
     * @return array{categories: Collection<int, Category>, serviceCount: int}
     */
    public static function forStore(?int $storeId = null): array
    {
        $storeId = $storeId ?? (int) current_store_id();
        $orgId = Store::query()->whereKey($storeId)->value('organization_id');

        return self::forOrganization($orgId ? (int) $orgId : 0);
    }
}
