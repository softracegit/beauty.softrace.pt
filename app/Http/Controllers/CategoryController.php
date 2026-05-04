<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Agent;
use App\Models\Category;
use App\Models\Extra;
use App\Models\ExtraCategory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories
     */
    public function index(Request $request)
    {
        // Only return JSON if explicitly requested via AJAX (lista + counts para badges)
        if ($request->ajax() && $request->header('X-Requested-With') === 'XMLHttpRequest') {
            $categories = Category::forStore(current_store_id())->orderBy('sort_order')->withCount('services')->get();

            return response()->json($categories);
        }

        $selectedCategory = null; // por defeito: "Todas as categorias"
        $categories = Category::forStore(current_store_id())->orderBy('sort_order')
            ->with(['services' => fn ($q) => $q->with('agents', 'extras', 'options')->orderBy('sort_order')])
            ->withCount('services')
            ->get();
        $agents = Agent::forStore(current_store_id())->whereHas('user', fn ($q) => $q->whereIn('role', [User::ROLE_PRESTADOR, User::ROLE_TECNICO]))
            ->orderBy('name')
            ->get();
        $extras = Extra::query()
            ->whereHas('extraCategory', fn ($q) => $q->where('store_id', current_store_id()))
            ->with('extraCategory')
            ->orderBy('extra_category_id')
            ->orderBy('sort_order')
            ->get();
        $extraCategories = ExtraCategory::forStore(current_store_id())->orderBy('sort_order')
            ->with(['extras' => fn ($q) => $q->orderBy('sort_order')])
            ->get();

        return view('services.index', [
            'categories' => $categories,
            'selectedCategory' => $selectedCategory,
            'agents' => $agents,
            'extras' => $extras,
            'extraCategories' => $extraCategories,
        ]);
    }

    /**
     * Display the specified category (for AJAX)
     */
    public function show(Request $request, Category $category)
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($category);
        }

        return redirect()->route('services.index');
    }

    /**
     * Store a newly created category
     */
    public function store(StoreCategoryRequest $request)
    {
        $data = $request->validated();

        // Set sort_order if not provided
        if (! isset($data['sort_order'])) {
            $maxOrder = Category::forStore(current_store_id())->max('sort_order') ?? 0;
            $data['sort_order'] = $maxOrder + 1;
        }

        $data['store_id'] = current_store_id();
        $category = Category::create($data);

        // Check if it's an AJAX request by checking headers
        if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest' || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Categoria criada com sucesso.',
                'category' => $category,
            ]);
        }

        return redirect()->route('services.index')
            ->with('success', 'Categoria criada com sucesso.');
    }

    /**
     * Update the specified category
     */
    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $category->update($request->validated());

        if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest' || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Categoria atualizada com sucesso.',
                'category' => $category->fresh(),
            ]);
        }

        return redirect()->route('services.index')
            ->with('success', 'Categoria atualizada com sucesso.');
    }

    /**
     * Remove the specified category
     */
    public function destroy(Request $request, Category $category)
    {
        // Check if category has services
        if ($category->services()->count() > 0) {
            if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest' || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não é possível eliminar uma categoria que possui serviços.',
                ], 422);
            }

            return redirect()->route('services.index')
                ->with('error', 'Não é possível eliminar uma categoria que possui serviços.');
        }

        $category->delete();

        if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest' || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Categoria eliminada com sucesso.',
            ]);
        }

        return redirect()->route('services.index')
            ->with('success', 'Categoria eliminada com sucesso.');
    }

    /**
     * Reorder categories
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['required', 'integer', 'exists:categories,id'],
        ]);

        $idsInStore = Category::forStore(current_store_id())
            ->whereIn('id', $request->order)
            ->pluck('id')
            ->count();
        if ($idsInStore !== count($request->order)) {
            return response()->json([
                'success' => false,
                'message' => 'Ordem inválida para esta loja.',
            ], 422);
        }

        foreach ($request->order as $index => $categoryId) {
            Category::forStore(current_store_id())->whereKey($categoryId)->update(['sort_order' => $index + 1]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ordem das categorias atualizada com sucesso.',
        ]);
    }
}
