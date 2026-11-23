<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaterialCategoryRequest;
use App\Http\Requests\StoreMaterialRequest;
use App\Http\Requests\UpdateMaterialCategoryRequest;
use App\Http\Requests\UpdateMaterialRequest;
use App\Models\Material;
use App\Models\MaterialCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class MaterialController extends Controller
{
    public function index(Request $request): View
    {
        $organizationId = current_organization_id();
        $selectedCategory = null;
        $viewArchive = false;
        $archivedMaterials = collect();

        $archivedMaterialsCount = Material::query()
            ->onlyTrashed()
            ->forOrganization($organizationId)
            ->count();

        $categories = MaterialCategory::forOrganization($organizationId)
            ->orderBy('sort_order')
            ->withCount('materials')
            ->with(['materials' => fn ($q) => $q->ordered()])
            ->get();

        $categoryId = $request->get('category_id');
        if ($categoryId === 'archive') {
            $viewArchive = true;
            $archivedMaterials = Material::query()
                ->onlyTrashed()
                ->forOrganization($organizationId)
                ->with('materialCategory')
                ->orderByDesc('deleted_at')
                ->orderBy('name')
                ->get();
        } elseif ($categoryId && $categoryId !== 'all') {
            $selectedCategory = $categories->firstWhere('id', (int) $categoryId)
                ?? MaterialCategory::forOrganization($organizationId)->find($categoryId);
            if ($selectedCategory && ! $selectedCategory->relationLoaded('materials')) {
                $selectedCategory->load(['materials' => fn ($q) => $q->ordered()]);
            }
        }

        $materials = $selectedCategory ? $selectedCategory->materials : collect();

        return view('materials.index', [
            'categories' => $categories,
            'selectedCategory' => $selectedCategory,
            'viewArchive' => $viewArchive,
            'archivedMaterials' => $archivedMaterials,
            'archivedMaterialsCount' => $archivedMaterialsCount,
            'materials' => $materials,
        ]);
    }

    public function list(): JsonResponse
    {
        $materials = Material::forOrganization(current_organization_id())
            ->active()
            ->with('materialCategory:id,name,color')
            ->ordered()
            ->get()
            ->map(fn (Material $m) => $m->toChoiceArray() + [
                'material_category_id' => (int) $m->material_category_id,
                'category_name' => $m->materialCategory?->name,
            ])
            ->values()
            ->all();

        return response()->json(['materials' => $materials]);
    }

    public function store(StoreMaterialRequest $request): JsonResponse
    {
        $data = $request->safe()->except(['photo']);
        $organizationId = current_organization_id();
        $category = MaterialCategory::forOrganization($organizationId)->findOrFail($data['material_category_id']);

        if (! isset($data['sort_order'])) {
            $data['sort_order'] = (Material::query()->where('material_category_id', $category->id)->max('sort_order') ?? 0) + 1;
        }
        $data['organization_id'] = $organizationId;
        $data['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : true;

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('materials', 'public');
        }

        $material = Material::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Material criado com sucesso.',
            'material' => $material->fresh('materialCategory')->toChoiceArray() + [
                'material_category_id' => (int) $material->material_category_id,
                'category_name' => $material->materialCategory?->name,
                'sort_order' => (int) $material->sort_order,
                'is_active' => (bool) $material->is_active,
            ],
        ]);
    }

    public function show(Material $material): JsonResponse
    {
        return response()->json([
            'id' => $material->id,
            'material_category_id' => (int) $material->material_category_id,
            'name' => $material->name,
            'code' => $material->code,
            'brand' => $material->brand,
            'finish' => $material->finish,
            'hex' => $material->hex,
            'stock' => $material->stock,
            'photo_path' => $material->photo_path,
            'photo_url' => $material->photoUrl(),
            'sort_order' => (int) $material->sort_order,
            'is_active' => (bool) $material->is_active,
            'used_in' => $material->clinicalFieldUsages(),
        ]);
    }

    public function update(UpdateMaterialRequest $request, Material $material): JsonResponse
    {
        $data = $request->safe()->except(['photo', 'remove_photo']);
        $organizationId = current_organization_id();
        MaterialCategory::forOrganization($organizationId)->findOrFail($data['material_category_id']);

        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        if ($request->boolean('remove_photo') && $material->photo_path) {
            if (Storage::disk('public')->exists($material->photo_path)) {
                Storage::disk('public')->delete($material->photo_path);
            }
            $data['photo_path'] = null;
        }

        if ($request->hasFile('photo')) {
            if ($material->photo_path && Storage::disk('public')->exists($material->photo_path)) {
                Storage::disk('public')->delete($material->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('materials', 'public');
        }

        $material->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Material atualizado com sucesso.',
            'material' => $material->fresh('materialCategory')->toChoiceArray() + [
                'material_category_id' => (int) $material->material_category_id,
                'sort_order' => (int) $material->sort_order,
                'is_active' => (bool) $material->is_active,
            ],
        ]);
    }

    public function destroy(Material $material): JsonResponse
    {
        $material->delete();

        return response()->json([
            'success' => true,
            'message' => 'Material movido para o Arquivo.',
        ]);
    }

    public function restore(int $id): JsonResponse
    {
        $material = Material::withTrashed()
            ->whereKey($id)
            ->forOrganization(current_organization_id())
            ->first();

        if (! $material) {
            return response()->json([
                'success' => false,
                'message' => 'Material não encontrado no Arquivo.',
            ], 404);
        }

        if ($material->trashed()) {
            $material->restore();
        }

        return response()->json([
            'success' => true,
            'message' => 'Material restaurado com sucesso.',
            'material' => $material->fresh('materialCategory'),
        ]);
    }

    public function reorder(Request $request, MaterialCategory $materialCategory): JsonResponse
    {
        $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['required', 'integer', 'exists:materials,id'],
        ]);

        if ((int) $materialCategory->organization_id !== (int) current_organization_id()) {
            abort(404);
        }

        $ids = Material::query()
            ->where('material_category_id', $materialCategory->id)
            ->whereIn('id', $request->order)
            ->pluck('id')
            ->all();

        if (count($ids) !== count($request->order)) {
            return response()->json([
                'success' => false,
                'message' => 'Alguns materiais não pertencem a esta categoria.',
            ], 422);
        }

        foreach ($request->order as $index => $materialId) {
            Material::query()->whereKey($materialId)->update(['sort_order' => $index + 1]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ordem dos materiais atualizada com sucesso.',
        ]);
    }

    public function showCategory(MaterialCategory $materialCategory): JsonResponse
    {
        return response()->json($materialCategory);
    }

    public function storeCategory(StoreMaterialCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $organizationId = current_organization_id();
        if (! isset($data['sort_order'])) {
            $data['sort_order'] = (MaterialCategory::forOrganization($organizationId)->max('sort_order') ?? 0) + 1;
        }
        $data['organization_id'] = $organizationId;
        $category = MaterialCategory::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Categoria criada com sucesso.',
            'category' => $category,
        ]);
    }

    public function updateCategory(UpdateMaterialCategoryRequest $request, MaterialCategory $materialCategory): JsonResponse
    {
        $materialCategory->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Categoria atualizada com sucesso.',
            'category' => $materialCategory->fresh(),
        ]);
    }

    public function destroyCategory(MaterialCategory $materialCategory): JsonResponse
    {
        if ($materialCategory->materials()->withTrashed()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Não é possível eliminar uma categoria que possui materiais (incluindo no Arquivo).',
            ], 422);
        }

        $materialCategory->delete();

        return response()->json([
            'success' => true,
            'message' => 'Categoria eliminada com sucesso.',
        ]);
    }
}
