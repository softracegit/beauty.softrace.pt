<?php

namespace App\Http\Controllers;

use App\Actions\SyncServiceOptionsAction;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\SyncServiceTecnicosRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Models\Agent;
use App\Models\Category;
use App\Models\Service;
use App\Models\Store;
use App\Models\User;
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
            ->with(['services' => fn ($q) => $q->with('agents')->withCount(['extras', 'fees'])->with([
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
            ->withCount(['extras', 'fees'])
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

        if ($request->has('agent_ids')) {
            $this->syncAgentsForCurrentStore($service, $request->input('agent_ids', []));
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

        // Formulário só lista técnicos da loja actual — preservar associações das outras lojas.
        if ($request->has('agent_ids')) {
            $this->syncAgentsForCurrentStore($service, $request->input('agent_ids', []));
        } else {
            $this->syncAgentsForCurrentStore($service, []);
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

                $service->agents()->sync(array_values(array_unique(array_merge($otherStoreAgentIds, $ids))));
            }
        });

        return redirect()
            ->route('services.tecnicos', [StoreContextPreference::QUERY_STORE => $storeId])
            ->with('success', 'Associações entre serviços e técnicos atualizadas.');
    }

    /**
     * Sync técnicos da loja actual sem remover agent_service de outras lojas.
     *
     * @param  list<int|string>|array<int|string, mixed>  $agentIds
     */
    private function syncAgentsForCurrentStore(Service $service, array $agentIds): void
    {
        $storeId = current_store_id();
        $allowed = Agent::query()
            ->activeServiceProviders($storeId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $ids = array_values(array_intersect(
            array_values(array_unique(array_map('intval', $agentIds))),
            $allowed
        ));

        $otherStoreAgentIds = $service->agents()
            ->where('agents.store_id', '!=', $storeId)
            ->pluck('agents.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $service->agents()->sync(array_values(array_unique(array_merge($otherStoreAgentIds, $ids))));
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
