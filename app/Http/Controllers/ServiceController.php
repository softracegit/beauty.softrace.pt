<?php

namespace App\Http\Controllers;

use App\Actions\SyncServiceOptionsAction;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\SyncServiceExtraEquipaRequest;
use App\Http\Requests\SyncServiceTecnicosRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Models\Agent;
use App\Models\Category;
use App\Models\Service;
use App\Models\Store;
use App\Models\User;
use App\Support\AgentServiceExtras;
use App\Support\CurrentStore;
use App\Support\DateTimeDisplay;
use App\Support\StoreContextPreference;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        $groups = Category::forOrganization(current_organization_id())->orderBy('sort_order')
            ->with(['services' => fn ($q) => $q
                ->with([
                    'agents' => fn ($aq) => $aq->with(['store:id,name'])->orderBy('agents.name'),
                    'options' => fn ($oq) => $oq->orderBy('sort_order'),
                ])
                ->withCount([
                    'extras',
                    'fees',
                    'agents',
                    'futureActiveMarcacoes as future_marcacoes_count',
                ])
                ->orderBy('sort_order')])
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
            ->with([
                'agents' => fn ($aq) => $aq->with(['store:id,name'])->orderBy('agents.name'),
                'options' => fn ($q) => $q->orderBy('sort_order'),
            ])
            ->withCount([
                'extras',
                'fees',
                'agents',
                'futureActiveMarcacoes as future_marcacoes_count',
            ])
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
     * PDF do catálogo de serviços (categoria, nome, preço online, duração).
     */
    public function exportPdf(Request $request): Response
    {
        $validated = $request->validate([
            'scope' => ['nullable', 'string', 'in:all,visible'],
        ]);

        $scope = (string) ($validated['scope'] ?? 'all');
        $onlyVisible = $scope === 'visible';

        $storeId = current_store_id();
        $organizationId = current_organization_id();
        $store = app(CurrentStore::class)->get();

        $categoriesQuery = Category::forOrganization($organizationId)->orderBy('sort_order');
        if ($onlyVisible) {
            $categoriesQuery->visibleInBooking();
        }

        $categories = $categoriesQuery
            ->with(['services' => function ($q) use ($onlyVisible) {
                if ($onlyVisible) {
                    $q->visibleInBooking();
                }
                $q->with([
                    'options' => fn ($oq) => $oq->orderBy('sort_order'),
                ])->orderBy('sort_order');
            }])
            ->get()
            ->filter(fn (Category $category) => $category->services->isNotEmpty())
            ->values();

        $totalRows = $categories->sum(function (Category $category): int {
            return $category->services->sum(function (Service $service): int {
                return $service->options->isNotEmpty() ? $service->options->count() : 1;
            });
        });

        $pdf = Pdf::loadView('services.pdf.catalog', [
            'categories' => $categories,
            'storeName' => $store->name,
            'appName' => config('app.name'),
            'generatedAt' => DateTimeDisplay::formatInstant(now(), $storeId, 'd/m/Y H:i'),
            'totalRows' => $totalRows,
            'scopeLabel' => $onlyVisible ? 'Só visíveis no booking' : 'Todos os serviços',
        ])->setPaper('a4', 'portrait');

        $suffix = $onlyVisible ? 'visiveis' : 'todos';
        $filename = 'servicos_'.$suffix.'_'.now()->format('Y-m-d_His').'.pdf';

        return $pdf->stream($filename);
    }

    /**
     * Store a newly created service
     */
    public function store(StoreServiceRequest $request): JsonResponse
    {
        $data = $request->serviceAttributes();
        $organizationId = current_organization_id();
        $data['organization_id'] = $organizationId;
        $hasOptions = $request->boolean('has_options');

        // Set sort_order if not provided
        if (! isset($data['sort_order'])) {
            $maxOrder = Service::forOrganization($organizationId)->where('category_id', $data['category_id'])->max('sort_order') ?? 0;
            $data['sort_order'] = $maxOrder + 1;
        }

        if (array_key_exists('online_price', $data) && ($data['online_price'] === '' || $data['online_price'] === null)) {
            $data['online_price'] = null;
        }

        $service = Service::create($data);

        $this->syncServiceOptions->execute($service, $hasOptions, $hasOptions ? $request->optionRows() : []);

        if ($request->boolean('sync_extras')) {
            $this->syncExtrasGrantingAgents($service, $request->input('extra_ids', []));
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

        if ($request->boolean('sync_extras')) {
            $this->syncExtrasGrantingAgents($service, $request->input('extra_ids', []));
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
     * Serviços no arquivo (soft-deleted).
     */
    public function archived(): JsonResponse
    {
        $organizationId = current_organization_id();
        $services = Service::forOrganization($organizationId)
            ->onlyTrashed()
            ->with([
                'category' => fn ($q) => $q->withTrashed(),
                'agents' => fn ($aq) => $aq->with(['store:id,name'])->orderBy('agents.name'),
                'options' => fn ($q) => $q->orderBy('sort_order'),
            ])
            ->withCount([
                'extras',
                'fees',
                'agents',
                'futureActiveMarcacoes as future_marcacoes_count',
            ])
            ->orderByDesc('deleted_at')
            ->orderBy('name')
            ->get();

        $archivedCategories = Category::forOrganization($organizationId)
            ->onlyTrashed()
            ->withCount(['services as trashed_services_count' => fn ($q) => $q->onlyTrashed()])
            ->orderByDesc('deleted_at')
            ->orderBy('name')
            ->get();

        return response()->json([
            'services' => $services,
            'archived_categories' => $archivedCategories,
            'archived_services_count' => $services->count(),
        ]);
    }

    /**
     * Remove the specified service (soft delete → Arquivo).
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

        $categoryId = (int) $service->category_id;
        $service->delete();

        // Se não restam serviços activos, a categoria passa também para o Arquivo.
        $category = Category::query()->whereKey($categoryId)->first();
        if ($category && $category->services()->count() === 0) {
            $category->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Serviço movido para o Arquivo.',
        ]);
    }

    /**
     * Restaura um serviço do Arquivo.
     *
     * Usa id escalar (não route-model binding): o SubstituteBindings do grupo web
     * corre antes de auth/set.current.store, e o scope de organização falhava nos soft-deleted.
     */
    public function restore(int $id): JsonResponse
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $user->canDeleteCatalogServicesAndCategories()) {
            return response()->json([
                'success' => false,
                'message' => 'Sem permissão para restaurar serviços.',
            ], 403);
        }

        $service = Service::withTrashed()
            ->forOrganization(current_organization_id())
            ->whereKey($id)
            ->first();

        if (! $service) {
            return response()->json([
                'success' => false,
                'message' => 'Serviço não encontrado no Arquivo.',
            ], 404);
        }

        if ($service->trashed()) {
            $service->restore();
        }

        $category = Category::withTrashed()->whereKey($service->category_id)->first();
        if ($category && $category->trashed()) {
            $category->restore();
        }

        return response()->json([
            'success' => true,
            'message' => 'Serviço restaurado com sucesso.',
            'service' => $service->fresh(['category']),
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

        $organizationId = current_organization_id();

        // Verify all services belong to the category
        $serviceIds = Service::forOrganization($organizationId)->whereIn('id', $request->order)
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
            Service::forOrganization($organizationId)->whereKey($serviceId)->update(['sort_order' => $index + 1]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ordem dos serviços atualizada com sucesso.',
        ]);
    }

    /**
     * Matriz serviços × técnicos (agentes com perfil prestador/técnico).
     * Sempre uma loja concreta (cookie / ?loja=) — sem «Todas», para a tabela não explodir.
     */
    public function tecnicos(Request $request): View
    {
        $storeId = $this->resolveCatalogStoreId($request);
        $organizationId = current_organization_id();

        $categories = Category::forOrganization($organizationId)->orderBy('sort_order')
            ->with(['services' => fn ($q) => $q->with('agents:id')->orderBy('sort_order')])
            ->get();

        $agents = Agent::query()
            ->activeServiceProviders($storeId)
            ->orderBy('agenda_order')
            ->orderBy('name')
            ->get(['id', 'name', 'avatar', 'agenda_order']);

        $store = Store::query()->find($storeId);

        return view('services.tecnicos', [
            'categories' => $categories,
            'agents' => $agents,
            'matrixStoreId' => $storeId,
            'matrixStore' => $store,
        ]);
    }

    /**
     * Persistir associações serviço ↔ técnico (pivot agent_service).
     */
    public function syncTecnicos(SyncServiceTecnicosRequest $request): RedirectResponse
    {
        $storeId = $this->resolveCatalogStoreId($request);
        $organizationId = current_organization_id();
        $assignments = $request->validated('assignments', []);

        $allowedAgentIds = Agent::query()
            ->activeServiceProviders($storeId)
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($assignments, $allowedAgentIds, $organizationId, $storeId): void {
            foreach (Service::forOrganization($organizationId)->pluck('id') as $serviceId) {
                $service = Service::forOrganization($organizationId)->whereKey($serviceId)->first();
                if (! $service) {
                    continue;
                }

                $ids = isset($assignments[$serviceId])
                    ? array_values(array_unique(array_map('intval', (array) $assignments[$serviceId])))
                    : [];
                $ids = array_values(array_intersect($ids, $allowedAgentIds));

                // Keep agents from other stores; only replace associations for this store.
                $otherStoreAgentIds = $service->agents()
                    ->where('agents.store_id', '!=', $storeId)
                    ->pluck('agents.id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $previousAgentIds = $service->agents()->pluck('agents.id')->map(fn ($id) => (int) $id)->all();
                $nextAgentIds = array_values(array_unique(array_merge($otherStoreAgentIds, $ids)));
                $service->agents()->sync($nextAgentIds);
                AgentServiceExtras::syncForServiceAgents((int) $service->id, $previousAgentIds, $nextAgentIds);
            }
        });

        return redirect()
            ->route('services.tecnicos', [StoreContextPreference::QUERY_STORE => $storeId])
            ->with('success', 'Associações entre serviços e técnicos atualizadas.');
    }

    /**
     * Matriz extra × técnico, dentro de cada serviço que tem extras.
     */
    public function extrasEquipa(Request $request): View
    {
        $storeId = $this->resolveCatalogStoreId($request);
        $organizationId = current_organization_id();

        $categories = Category::forOrganization($organizationId)->orderBy('sort_order')
            ->with(['services' => fn ($q) => $q->with([
                'agents:id',
                'extras' => fn ($eq) => $eq->orderBy('sort_order'),
            ])->orderBy('sort_order')])
            ->get();

        $agents = Agent::query()
            ->activeServiceProviders($storeId)
            ->orderBy('agenda_order')
            ->orderBy('name')
            ->get(['id', 'name', 'avatar', 'agenda_order']);

        $assigned = [];
        if ($agents->isNotEmpty()) {
            $rows = DB::table('agent_service_extra')
                ->whereIn('agent_id', $agents->pluck('id'))
                ->get(['agent_id', 'service_id', 'extra_id']);
            foreach ($rows as $row) {
                $assigned[(int) $row->service_id.'-'.(int) $row->extra_id.'-'.(int) $row->agent_id] = true;
            }
        }

        $store = Store::query()->find($storeId);

        return view('services.extras-equipa', [
            'categories' => $categories,
            'agents' => $agents,
            'assigned' => $assigned,
            'matrixStoreId' => $storeId,
            'matrixStore' => $store,
        ]);
    }

    public function syncExtrasEquipa(SyncServiceExtraEquipaRequest $request): RedirectResponse
    {
        $storeId = $this->resolveCatalogStoreId($request);
        $organizationId = current_organization_id();
        $assignments = $request->validated('assignments', []);

        $allowedAgentIds = Agent::query()
            ->activeServiceProviders($storeId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $services = Service::forOrganization($organizationId)
            ->with(['agents:id', 'extras:id'])
            ->get();

        $catalogPairs = [];
        $agentsByService = [];
        foreach ($services as $service) {
            if ($service->extras->isEmpty()) {
                continue;
            }
            $agentsByService[(int) $service->id] = $service->agents->pluck('id')->map(fn ($id) => (int) $id)->all();
            foreach ($service->extras as $extra) {
                $catalogPairs[] = [
                    'service_id' => (int) $service->id,
                    'extra_id' => (int) $extra->id,
                ];
            }
        }

        $wanted = [];
        foreach ($assignments as $serviceId => $extras) {
            if (! is_array($extras)) {
                continue;
            }
            foreach ($extras as $extraId => $agentIds) {
                $wanted[(int) $serviceId][(int) $extraId] = array_values(array_intersect(
                    array_map('intval', (array) $agentIds),
                    $allowedAgentIds,
                ));
            }
        }

        DB::transaction(function () use ($catalogPairs, $agentsByService, $wanted, $allowedAgentIds): void {
            AgentServiceExtras::replaceStoreSelections($catalogPairs, $agentsByService, $wanted, $allowedAgentIds);
        });

        return redirect()
            ->route('services.extras-equipa', [StoreContextPreference::QUERY_STORE => $storeId])
            ->with('success', 'Extras por técnico atualizados.');
    }

    /**
     * @param  list<int|string>|array<int|string, mixed>  $extraIds
     */
    private function syncExtrasGrantingAgents(Service $service, array $extraIds): void
    {
        $previousExtraIds = $service->extras()->pluck('extras.id')->map(fn ($id) => (int) $id)->all();
        $nextExtraIds = array_values(array_unique(array_map('intval', $extraIds)));
        $service->extras()->sync($nextExtraIds);
        AgentServiceExtras::syncForServiceExtras((int) $service->id, $previousExtraIds, $nextExtraIds);
    }

    /**
     * Loja concreta para catálogo / matriz (nunca «todas»).
     * Aceita ?loja= / body loja= (POST do guardar) e persiste no cookie.
     */
    private function resolveCatalogStoreId(Request $request): int
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $accessibleIds = $user->accessibleStores()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $fromRequest = StoreContextPreference::parseStoreIdParam(
            $request->input(StoreContextPreference::QUERY_STORE)
        );
        $storeId = ($fromRequest !== null && in_array($fromRequest, $accessibleIds, true))
            ? $fromRequest
            : StoreContextPreference::resolveStoreId($user, $request);

        StoreContextPreference::persist($request, $storeId);

        $store = Store::query()->find($storeId);
        if ($store) {
            app(CurrentStore::class)->set($store);
        }

        return $storeId;
    }
}
