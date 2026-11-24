<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExtraCategoryRequest;
use App\Http\Requests\UpdateExtraCategoryRequest;
use App\Models\ExtraCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExtraCategoryController extends Controller
{
    /**
     * Reordenar categorias de extras (sidebar).
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['required', 'integer', 'exists:extra_categories,id'],
        ]);

        $organizationId = current_organization_id();
        $idsInOrg = ExtraCategory::forOrganization($organizationId)
            ->whereIn('id', $request->order)
            ->pluck('id')
            ->count();
        if ($idsInOrg !== count($request->order)) {
            return response()->json([
                'success' => false,
                'message' => 'Ordem inválida para esta organização.',
            ], 422);
        }

        foreach ($request->order as $index => $categoryId) {
            ExtraCategory::forOrganization($organizationId)
                ->whereKey($categoryId)
                ->update(['sort_order' => $index + 1]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ordem das categorias de extras atualizada com sucesso.',
        ]);
    }

    public function store(StoreExtraCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $organizationId = current_organization_id();
        if (! isset($data['sort_order'])) {
            $data['sort_order'] = (ExtraCategory::forOrganization($organizationId)->max('sort_order') ?? 0) + 1;
        }
        if (empty($data['color'])) {
            $data['color'] = '#6c757d';
        }
        $data['organization_id'] = $organizationId;
        $category = ExtraCategory::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Categoria criada com sucesso.',
            'category' => $category,
        ]);
    }

    public function update(UpdateExtraCategoryRequest $request, ExtraCategory $extraCategory): JsonResponse
    {
        $extraCategory->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Categoria atualizada com sucesso.',
            'category' => $extraCategory->fresh(),
        ]);
    }

    public function destroy(ExtraCategory $extraCategory): JsonResponse
    {
        $extraCategory->delete();

        return response()->json([
            'success' => true,
            'message' => 'Categoria eliminada com sucesso.',
        ]);
    }
}
