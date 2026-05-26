<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Support\Collection;

class ServiceCategoriesForAssociation
{
    public const UNCATEGORIZED_CATEGORY_KEY = -1;

    /**
     * Categorias da loja com serviços agrupados (cada serviço aparece uma única vez).
     *
     * @return array{categories: Collection<int, Category>, serviceCount: int}
     */
    public static function forStore(?int $storeId = null): array
    {
        $storeId = $storeId ?? (int) current_store_id();

        $categories = Category::forStore($storeId)
            ->orderBy('sort_order')
            ->get();

        $allServices = Service::forStore($storeId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $validCategoryIds = $categories->pluck('id')->map(fn ($id) => (int) $id)->all();
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
                'store_id' => $storeId,
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
}
