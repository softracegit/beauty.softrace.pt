<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExtraCategoryRequest;
use App\Http\Requests\StoreExtraRequest;
use App\Http\Requests\UpdateExtraCategoryRequest;
use App\Http\Requests\UpdateExtraRequest;
use App\Models\Extra;
use App\Models\ExtraCategory;
use App\Models\Service;
use App\Support\AgentServiceExtras;
use App\Support\ServiceCategoriesForAssociation;
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
        $organizationId = current_organization_id();
        $selectedCategory = null;
        $viewArchive = false;
        $archivedExtras = collect();

        $archivedExtrasCount = Extra::query()
            ->onlyTrashed()
            ->whereHas('extraCategory', fn ($q) => $q->where('organization_id', $organizationId))
            ->count();

        $extrasWithMembers = fn ($q) => $q
            ->with([
                'extraCategory',
                'services',
                'agents' => fn ($aq) => $aq->with(['store:id,name'])->orderBy('agents.name'),
            ])
            ->withCount('services')
            ->withStoreAgentsCount()
            ->orderBy('sort_order');

        $categories = ExtraCategory::forOrganization($organizationId)->orderBy('sort_order')
            ->withCount('extras')
            ->with(['extras' => $extrasWithMembers])
            ->get();

        $categoryId = $request->get('category_id');
        if ($categoryId === 'archive') {
            $viewArchive = true;
            $archivedExtras = Extra::query()
                ->onlyTrashed()
                ->whereHas('extraCategory', fn ($q) => $q->where('organization_id', $organizationId))
                ->with([
                    'extraCategory',
                    'services',
                    'agents' => fn ($aq) => $aq->with(['store:id,name'])->orderBy('agents.name'),
                ])
                ->withCount('services')
                ->withStoreAgentsCount()
                ->orderByDesc('deleted_at')
                ->orderBy('name')
                ->get();
        } elseif ($categoryId && $categoryId !== 'all') {
            $selectedCategory = $categories->firstWhere('id', (int) $categoryId) ?? ExtraCategory::forOrganization($organizationId)->find($categoryId);
            if ($selectedCategory && ! $selectedCategory->relationLoaded('extras')) {
                $selectedCategory->load(['extras' => $extrasWithMembers]);
            }
        }

        $extras = $selectedCategory
            ? $selectedCategory->extras
            : collect();

        $services = Service::forOrganization($organizationId)->with('category')->orderBy('name')->get();
        $association = ServiceCategoriesForAssociation::forOrganization($organizationId);

        return view('extras.index', [
            'categories' => $categories,
            'selectedCategory' => $selectedCategory,
            'viewArchive' => $viewArchive,
            'archivedExtras' => $archivedExtras,
            'archivedExtrasCount' => $archivedExtrasCount,
            'extras' => $extras,
            'services' => $services,
            'serviceCategories' => $association['categories'],
        ]);
    }

    public function create(): View
    {
        $organizationId = current_organization_id();
        $categories = ExtraCategory::forOrganization($organizationId)->orderBy('sort_order')->get();
        $services = Service::forOrganization($organizationId)->with('category')->orderBy('name')->get();

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
            $nextServiceIds = array_values(array_unique(array_map('intval', (array) $serviceIds)));
            $extra->services()->sync($nextServiceIds);
            AgentServiceExtras::syncForExtraServices((int) $extra->id, [], $nextServiceIds);
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
                'short_description' => $extra->short_description ?? '',
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
        $organizationId = current_organization_id();
        $categories = ExtraCategory::forOrganization($organizationId)->orderBy('sort_order')->get();
        $services = Service::forOrganization($organizationId)->with('category')->orderBy('name')->get();

        return view('extras.edit', compact('extra', 'categories', 'services'));
    }

    public function update(UpdateExtraRequest $request, Extra $extra): JsonResponse
    {
        $data = $request->validated();
        $serviceIds = $data['service_ids'] ?? null;
        unset($data['service_ids']);

        $extra->update($data);
        if ($serviceIds !== null) {
            $previousServiceIds = $extra->services()->pluck('services.id')->map(fn ($id) => (int) $id)->all();
            $nextServiceIds = array_values(array_unique(array_map('intval', (array) $serviceIds)));
            $extra->services()->sync($nextServiceIds);
            AgentServiceExtras::syncForExtraServices((int) $extra->id, $previousServiceIds, $nextServiceIds);
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
            'message' => 'Extra movido para o Arquivo.',
        ]);
    }

    /**
     * Restaura um extra do Arquivo (id escalar — evita binding antes de auth/loja).
     */
    public function restore(int $id): JsonResponse
    {
        $extra = Extra::withTrashed()
            ->whereKey($id)
            ->whereHas('extraCategory', fn ($q) => $q->where('organization_id', current_organization_id()))
            ->first();

        if (! $extra) {
            return response()->json([
                'success' => false,
                'message' => 'Extra não encontrado no Arquivo.',
            ], 404);
        }

        if ($extra->trashed()) {
            $extra->restore();
        }

        return response()->json([
            'success' => true,
            'message' => 'Extra restaurado com sucesso.',
            'extra' => $extra->fresh(['extraCategory', 'services']),
        ]);
    }

    /**
     * Reordenar extras dentro de uma categoria.
     */
    public function reorder(Request $request, ExtraCategory $extraCategory): JsonResponse
    {
        $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['required', 'integer', 'exists:extras,id'],
        ]);

        $organizationId = current_organization_id();
        if ((int) $extraCategory->organization_id !== (int) $organizationId) {
            abort(404);
        }

        $extraIds = Extra::query()
            ->where('extra_category_id', $extraCategory->id)
            ->whereIn('id', $request->order)
            ->pluck('id')
            ->all();

        if (count($extraIds) !== count($request->order)) {
            return response()->json([
                'success' => false,
                'message' => 'Alguns extras não pertencem a esta categoria.',
            ], 422);
        }

        foreach ($request->order as $index => $extraId) {
            Extra::query()->whereKey($extraId)->update(['sort_order' => $index + 1]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ordem dos extras atualizada com sucesso.',
        ]);
    }

    /**
     * API: listar extras (por categoria ou todos) para AJAX.
     */
    public function list(Request $request): JsonResponse
    {
        $categoryId = $request->get('extra_category_id');
        $query = Extra::query()
            ->whereHas('extraCategory', fn ($q) => $q->where('organization_id', current_organization_id()))
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
        $organizationId = current_organization_id();
        if (! isset($data['sort_order'])) {
            $data['sort_order'] = (ExtraCategory::forOrganization($organizationId)->max('sort_order') ?? 0) + 1;
        }
        $data['organization_id'] = $organizationId;
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
        // Inclui extras no Arquivo: apagar a categoria faria cascade e apagava-os da BD.
        if ($extraCategory->extras()->withTrashed()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Não é possível eliminar uma categoria que possui extras (incluindo no Arquivo).',
            ], 422);
        }

        $extraCategory->delete();

        return response()->json([
            'success' => true,
            'message' => 'Categoria eliminada com sucesso.',
        ]);
    }
}
