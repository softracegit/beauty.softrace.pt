<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExtraCategoryRequest;
use App\Http\Requests\StoreExtraRequest;
use App\Http\Requests\UpdateExtraCategoryRequest;
use App\Http\Requests\UpdateExtraRequest;
use App\Models\Category;
use App\Models\Extra;
use App\Models\ExtraCategory;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExtraController extends Controller
{
    /**
     * Listagem de extras por categoria (vista igual ao catálogo de serviços).
     */
    public function index(Request $request): View
    {
        $selectedCategory = null;
        $categories = ExtraCategory::forStore(current_store_id())->orderBy('sort_order')
            ->withCount('extras')
            ->with(['extras' => fn ($q) => $q->with('extraCategory', 'services')->orderBy('sort_order')])
            ->get();

        $categoryId = $request->get('category_id');
        if ($categoryId && $categoryId !== 'all') {
            $selectedCategory = $categories->firstWhere('id', (int) $categoryId) ?? ExtraCategory::forStore(current_store_id())->find($categoryId);
            if ($selectedCategory && ! $selectedCategory->relationLoaded('extras')) {
                $selectedCategory->load(['extras' => fn ($q) => $q->with('extraCategory', 'services')->orderBy('sort_order')]);
            }
        }

        $extras = $selectedCategory
            ? $selectedCategory->extras
            : collect();

        $services = Service::forStore(current_store_id())->with('category')->orderBy('name')->get();
        $serviceCategories = Category::forStore(current_store_id())->orderBy('sort_order')
            ->with(['services' => fn ($q) => $q->orderBy('sort_order')])
            ->get();

        return view('extras.index', [
            'categories' => $categories,
            'selectedCategory' => $selectedCategory,
            'extras' => $extras,
            'services' => $services,
            'serviceCategories' => $serviceCategories,
        ]);
    }

    public function create(): View
    {
        $categories = ExtraCategory::forStore(current_store_id())->orderBy('sort_order')->get();
        $services = Service::forStore(current_store_id())->with('category')->orderBy('name')->get();

        return view('extras.create', compact('categories', 'services'));
    }

    public function store(StoreExtraRequest $request): JsonResponse
    {
        $data = $request->validated();
        $serviceIds = $data['service_ids'] ?? null;
        unset($data['service_ids']);

        if (! isset($data['sort_order'])) {
            $max = Extra::where('extra_category_id', $data['extra_category_id'])->max('sort_order') ?? 0;
            $data['sort_order'] = $max + 1;
        }

        $extra = Extra::create($data);
        if ($serviceIds !== null) {
            $extra->services()->sync($serviceIds);
        }

        return response()->json([
            'success' => true,
            'message' => 'Extra criado com sucesso.',
            'extra' => $extra->load(['extraCategory', 'services']),
        ]);
    }

    /**
     * Show extra (JSON for modal edit, or redirect to edit page).
     */
    public function show(Request $request, Extra $extra): JsonResponse|View
    {
        $extra->load('services');
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'id' => $extra->id,
                'extra_category_id' => $extra->extra_category_id,
                'name' => $extra->name,
                'description' => $extra->description ?? '',
                'price' => (float) $extra->price,
                'duration' => (int) $extra->duration,
                'service_ids' => $extra->services->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            ]);
        }

        return redirect()->route('extras.edit', $extra);
    }

    public function edit(Extra $extra): View
    {
        $extra->load('services');
        $categories = ExtraCategory::forStore(current_store_id())->orderBy('sort_order')->get();
        $services = Service::forStore(current_store_id())->with('category')->orderBy('name')->get();

        return view('extras.edit', compact('extra', 'categories', 'services'));
    }

    public function update(UpdateExtraRequest $request, Extra $extra): JsonResponse
    {
        $data = $request->validated();
        $serviceIds = $data['service_ids'] ?? null;
        unset($data['service_ids']);

        $extra->update($data);
        if ($serviceIds !== null) {
            $extra->services()->sync($serviceIds);
        }

        return response()->json([
            'success' => true,
            'message' => 'Extra atualizado com sucesso.',
            'extra' => $extra->fresh()->load(['extraCategory', 'services']),
        ]);
    }

    public function destroy(Extra $extra): JsonResponse
    {
        $extra->delete();

        return response()->json([
            'success' => true,
            'message' => 'Extra eliminado com sucesso.',
        ]);
    }

    /**
     * API: listar extras (por categoria ou todos) para AJAX.
     */
    public function list(Request $request): JsonResponse
    {
        $categoryId = $request->get('extra_category_id');
        $query = Extra::query()
            ->whereHas('extraCategory', fn ($q) => $q->where('store_id', current_store_id()))
            ->with('extraCategory')
            ->orderBy('sort_order');
        if ($categoryId) {
            $query->where('extra_category_id', $categoryId);
        }
        $extras = $query->get();

        return response()->json($extras);
    }

    /**
     * Mostrar uma categoria de extras (para AJAX no modal de edição).
     */
    public function showCategory(ExtraCategory $extraCategory): JsonResponse
    {
        return response()->json($extraCategory);
    }

    /**
     * Categorias de extras (CRUD para uso na index).
     */
    public function storeCategory(StoreExtraCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (! isset($data['sort_order'])) {
            $data['sort_order'] = (ExtraCategory::forStore(current_store_id())->max('sort_order') ?? 0) + 1;
        }
        $data['store_id'] = current_store_id();
        $category = ExtraCategory::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Categoria criada com sucesso.',
            'category' => $category,
        ]);
    }

    public function updateCategory(UpdateExtraCategoryRequest $request, ExtraCategory $extraCategory): JsonResponse
    {
        $extraCategory->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Categoria atualizada com sucesso.',
            'category' => $extraCategory->fresh(),
        ]);
    }

    public function destroyCategory(ExtraCategory $extraCategory): JsonResponse
    {
        $extraCategory->delete();

        return response()->json([
            'success' => true,
            'message' => 'Categoria eliminada com sucesso.',
        ]);
    }
}
