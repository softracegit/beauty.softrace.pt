<?php

namespace App\Http\Controllers;

use App\Models\ExtraCategory;
use App\Http\Requests\StoreExtraCategoryRequest;
use App\Http\Requests\UpdateExtraCategoryRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ExtraCategoryController extends Controller
{
    public function store(StoreExtraCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (!isset($data['sort_order'])) {
            $data['sort_order'] = (ExtraCategory::max('sort_order') ?? 0) + 1;
        }
        if (empty($data['color'])) {
            $data['color'] = '#6c757d';
        }
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
