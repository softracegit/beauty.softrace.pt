<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Agent;
use App\Models\Category;
use App\Models\Extra;
use App\Models\ExtraCategory;
use App\Models\Fee;
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
        $organizationId = current_organization_id();

        // Only return JSON if explicitly requested via AJAX (lista + counts para badges)
        if ($request->ajax() && $request->header('X-Requested-With') === 'XMLHttpRequest') {
            $categories = Category::forOrganization($organizationId)->orderBy('sort_order')->withCount('services')->get();

            return response()->json($categories);
        }

        $selectedCategory = null; // por defeito: "Todas as categorias"
        $categories = Category::forOrganization($organizationId)->orderBy('sort_order')
            ->with(['services' => fn ($q) => $q->with('agents', 'extras', 'fees', 'options')->withCount(['extras', 'fees'])->orderBy('sort_order')])
            ->withCount('services')
            ->get();
        $agents = Agent::query()
            ->activeServiceProviders(current_store_id())
            ->orderBy('name')
            ->get();
        $extras = Extra::query()
            ->whereHas('extraCategory', fn ($q) => $q->where('organization_id', $organizationId))
            ->with('extraCategory')
            ->orderBy('extra_category_id')
            ->orderBy('sort_order')
            ->get();
        $extraCategories = ExtraCategory::forOrganization($organizationId)->orderBy('sort_order')
            ->with(['extras' => fn ($q) => $q->orderBy('sort_order')])
            ->get();
        $fees = Fee::forOrganization($organizationId)->orderBy('sort_order')->orderBy('name')->get();

        return view('services.index', [
            'categories' => $categories,
            'selectedCategory' => $selectedCategory,
            'agents' => $agents,
            'extras' => $extras,
            'extraCategories' => $extraCategories,
            'fees' => $fees,
        ]);
    }

    /**
     * Display the specified category (for AJAX)
     */
    public function show(Request $request, Category $category)
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($category->fresh());
        }

        return redirect()->route('services.index');
    }

    /**
     * Store a newly created category
     */
    public function store(StoreCategoryRequest $request)
    {
        $data = $request->validated();
        $organizationId = current_organization_id();

        // Set sort_order if not provided
        if (! isset($data['sort_order'])) {
            $maxOrder = Category::forOrganization($organizationId)->max('sort_order') ?? 0;
            $data['sort_order'] = $maxOrder + 1;
        }

        $data['organization_id'] = $organizationId;
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
        $data = $request->validated();
        $data['hidden_from_booking'] = $request->boolean('hidden_from_booking');
        $category->update($data);

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
        $user = auth()->user();
        if (! $user instanceof User || ! $user->canDeleteCatalogServicesAndCategories()) {
            if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest' || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sem permissão para eliminar categorias.',
                ], 403);
            }

            return redirect()->route('services.index')
                ->with('error', 'Sem permissão para eliminar categorias.');
        }

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

        $organizationId = current_organization_id();
        $idsInOrg = Category::forOrganization($organizationId)
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
            Category::forOrganization($organizationId)->whereKey($categoryId)->update(['sort_order' => $index + 1]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ordem das categorias atualizada com sucesso.',
        ]);
    }
}
