<?php

namespace App\Http\Controllers;

use App\Actions\SyncServiceOptionsAction;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\SyncServiceTecnicosRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Models\Agent;
use App\Models\Category;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function __construct(
        private SyncServiceOptionsAction $syncServiceOptions,
    ) {}

    /**
     * All services grouped by category (AJAX) – for "Todas as categorias"
     */
    public function allGrouped(Request $request): JsonResponse
    {
        $groups = Category::forStore(current_store_id())->orderBy('sort_order')
            ->with(['services' => fn ($q) => $q->with('agents')->withCount('extras')->with([
                'options' => fn ($oq) => $oq->orderBy('sort_order'),
            ])->orderBy('sort_order')])
            ->get()
            ->map(fn (Category $cat) => [
                'category' => $cat,
                'services' => $cat->services,
            ]);

        return response()->json(['groups' => $groups]);
    }

    /**
     * Display services for a specific category (AJAX)
     */
    public function index(Request $request, ?Category $category = null): JsonResponse
    {
        if (! $category) {
            return response()->json(['services' => [], 'category' => null]);
        }

        $services = $category->services()
            ->with('agents')
            ->withCount('extras')
            ->with(['options' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'services' => $services,
            'category' => $category,
        ]);
    }

    /**
     * Display the specified service (for AJAX)
     */
    public function show(Service $service): JsonResponse
    {
        return response()->json([
            'service' => $service->load([
                'category',
                'agents',
                'extras',
                'fees',
                'options' => fn ($q) => $q->orderBy('sort_order'),
            ]),
        ]);
    }

    /**
     * Store a newly created service
     */
    public function store(StoreServiceRequest $request): JsonResponse
    {
        $data = $request->serviceAttributes();
        $data['store_id'] = current_store_id();
        $hasOptions = $request->boolean('has_options');

        // Set sort_order if not provided
        if (! isset($data['sort_order'])) {
            $maxOrder = Service::forStore(current_store_id())->where('category_id', $data['category_id'])->max('sort_order') ?? 0;
            $data['sort_order'] = $maxOrder + 1;
        }

        if (array_key_exists('online_price', $data) && ($data['online_price'] === '' || $data['online_price'] === null)) {
            $data['online_price'] = null;
        }

        $service = Service::create($data);

        $this->syncServiceOptions->execute($service, $hasOptions, $hasOptions ? $request->optionRows() : []);

        // Sync agents if provided
        if ($request->has('agent_ids')) {
            $service->agents()->sync($request->agent_ids);
        }
        if ($request->boolean('sync_extras')) {
            $service->extras()->sync($request->input('extra_ids', []));
        }
        if ($request->boolean('sync_fees')) {
            $service->fees()->sync($request->input('fee_ids', []));
        }

        return response()->json([
            'success' => true,
            'message' => 'Serviço criado com sucesso.',
            'service' => $service->fresh()->load([
                'category',
                'agents',
                'extras',
                'fees',
                'options' => fn ($q) => $q->orderBy('sort_order'),
            ]),
        ]);
    }

    /**
     * Update the specified service
     */
    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        $data = $request->serviceAttributes();
        $hasOptions = $request->boolean('has_options');

        if (! $hasOptions && array_key_exists('online_price', $data) && $data['online_price'] === '') {
            $data['online_price'] = null;
        }

        $service->update($data);

        $this->syncServiceOptions->execute($service, $hasOptions, $hasOptions ? $request->optionRows() : []);

        // Sync agents if provided
        if ($request->has('agent_ids')) {
            $service->agents()->sync($request->agent_ids);
        } else {
            $service->agents()->sync([]);
        }
        if ($request->boolean('sync_extras')) {
            $service->extras()->sync($request->input('extra_ids', []));
        }
        if ($request->boolean('sync_fees')) {
            $service->fees()->sync($request->input('fee_ids', []));
        }

        return response()->json([
            'success' => true,
            'message' => 'Serviço atualizado com sucesso.',
            'service' => $service->fresh()->load([
                'category',
                'agents',
                'extras',
                'options' => fn ($q) => $q->orderBy('sort_order'),
            ]),
        ]);
    }

    /**
     * Remove the specified service
     */
    public function destroy(Service $service): JsonResponse
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $user->canDeleteCatalogServicesAndCategories()) {
            return response()->json([
                'success' => false,
                'message' => 'Sem permissão para eliminar serviços.',
            ], 403);
        }

        $service->delete();

        return response()->json([
            'success' => true,
            'message' => 'Serviço eliminado com sucesso.',
        ]);
    }

    /**
     * Reorder services within a category
     */
    public function reorder(Request $request, Category $category): JsonResponse
    {
        $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['required', 'integer', 'exists:services,id'],
        ]);

        // Verify all services belong to the category
        $serviceIds = Service::forStore(current_store_id())->whereIn('id', $request->order)
            ->where('category_id', $category->id)
            ->pluck('id')
            ->toArray();

        if (count($serviceIds) !== count($request->order)) {
            return response()->json([
                'success' => false,
                'message' => 'Alguns serviços não pertencem a esta categoria.',
            ], 422);
        }

        foreach ($request->order as $index => $serviceId) {
            Service::forStore(current_store_id())->whereKey($serviceId)->update(['sort_order' => $index + 1]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ordem dos serviços atualizada com sucesso.',
        ]);
    }

    /**
     * Matriz serviços × técnicos (agentes com perfil prestador/técnico).
     */
    public function tecnicos(): View
    {
        $categories = Category::forStore(current_store_id())->orderBy('sort_order')
            ->with(['services' => fn ($q) => $q->with('agents:id')->orderBy('sort_order')])
            ->get();

        $agents = Agent::query()
            ->activeServiceProviders(current_store_id())
            ->orderBy('agenda_order')
            ->orderBy('name')
            ->get(['id', 'name', 'avatar', 'agenda_order']);

        return view('services.tecnicos', [
            'categories' => $categories,
            'agents' => $agents,
        ]);
    }

    /**
     * Persistir associações serviço ↔ técnico (pivot agent_service).
     */
    public function syncTecnicos(SyncServiceTecnicosRequest $request): RedirectResponse
    {
        $assignments = $request->validated('assignments', []);

        $allowedAgentIds = Agent::query()
            ->activeServiceProviders(current_store_id())
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($assignments, $allowedAgentIds): void {
            foreach (Service::forStore(current_store_id())->pluck('id') as $serviceId) {
                $ids = isset($assignments[$serviceId])
                    ? array_values(array_unique(array_map('intval', (array) $assignments[$serviceId])))
                    : [];
                $ids = array_values(array_intersect($ids, $allowedAgentIds));
                Service::forStore(current_store_id())->whereKey($serviceId)->first()?->agents()->sync($ids);
            }
        });

        return redirect()
            ->route('services.tecnicos')
            ->with('success', 'Associações entre serviços e técnicos atualizadas.');
    }
}
