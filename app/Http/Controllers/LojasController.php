<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\StoreBusinessSettingsService;
use App\Services\StoreSettingsActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LojasController extends Controller
{
    public function __construct(
        private readonly StoreBusinessSettingsService $storeBusinessSettings,
        private readonly StoreSettingsActivityLogger $settingsActivityLogger,
    ) {}
    public function index(Request $request): View
    {
        $this->authorize('create', Store::class);

        $user = auth()->user();
        $baseQuery = Store::query()->where('organization_id', $user->organization_id);

        $totalLojas = (clone $baseQuery)->count();
        $comEquipa = (clone $baseQuery)->has('agents')->count();
        $comServicos = (clone $baseQuery)
            ->whereHas('agents', fn ($q) => $q->whereHas('services'))
            ->count();
        $semServicosActivos = max(0, $totalLojas - $comServicos);

        $query = (clone $baseQuery)
            ->select('stores.*')
            ->withCount(['agents', 'clients', 'calendarEvents'])
            ->addSelect([
                'active_services_count' => DB::table('agent_service')
                    ->join('agents', 'agents.id', '=', 'agent_service.agent_id')
                    ->whereColumn('agents.store_id', 'stores.id')
                    ->selectRaw('count(distinct agent_service.service_id)'),
            ])
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address_line', 'like', "%{$search}%");
            });
        }

        $stores = $query->paginate(30)->withQueryString();

        return view('lojas.index', compact(
            'stores',
            'totalLojas',
            'comEquipa',
            'comServicos',
            'semServicosActivos',
        ));
    }

    public function create(): View
    {
        $this->authorize('create', Store::class);

        return view('lojas.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Store::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('stores', 'slug')],
            'address_line' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'max:32'],
            'maps_url' => ['required', 'url', 'max:512'],
        ], [
            'maps_url.required' => 'O link do mapa é obrigatório nesta loja.',
            'maps_url.url' => 'O link do mapa deve ser um URL válido.',
        ]);

        $slug = Str::slug((string) $validated['slug']);
        if ($slug === '') {
            $slug = $this->uniqueStoreSlug(Str::slug($validated['name']));
        }

        // Contactos / branding / horário herdam da empresa (null = herdar).
        $store = Store::query()->create([
            'organization_id' => auth()->user()->organization_id,
            'name' => $validated['name'],
            'slug' => $slug,
            'address_line' => $validated['address_line'],
            'city' => $validated['city'],
            'postal_code' => $validated['postal_code'],
            'maps_url' => $validated['maps_url'],
            'phone' => null,
            'email' => null,
            'timezone' => null,
            'weekly_schedule' => null,
            'website_url' => null,
            'instagram_url' => null,
            'logo' => null,
            'logo_email' => null,
            'logo_favicon' => null,
        ]);

        $this->seedDefaultCrmSettings($store);

        return redirect()
            ->route('lojas.index')
            ->with('success', 'Loja criada.');
    }

    public function edit(Request $request, Store $loja): View
    {
        $this->authorize('update', $loja);

        $data = $this->storeBusinessSettings->viewDataForStore($loja, $request->query('tab'));

        return view('lojas.edit', $data);
    }

    public function update(Request $request, Store $loja): RedirectResponse
    {
        $this->authorize('update', $loja);

        $changes = $this->storeBusinessSettings->applyToStore($request, $loja);

        $this->settingsActivityLogger->logSection(
            $loja,
            'negocio',
            'Dados da loja actualizados',
            $changes,
        );

        $tab = $this->storeBusinessSettings->resolveActiveTab($request->input('_active_tab'));

        return redirect()
            ->route('lojas.edit', ['loja' => $loja, 'tab' => $tab])
            ->with('success', 'Loja actualizada.');
    }

    public function destroy(Store $loja): RedirectResponse
    {
        $this->authorize('delete', $loja);

        $labels = $this->blockingDataLabels($loja);
        if ($labels !== []) {
            return redirect()
                ->back()
                ->with('error', 'Não é possível eliminar: a loja ainda tem '.implode(', ', $labels).'.');
        }

        DB::transaction(function () use ($loja): void {
            $loja->crmSettings()->delete();
            $loja->delete();
        });

        return redirect()
            ->route('lojas.index')
            ->with('success', 'Loja eliminada.');
    }

    /**
     * @return list<string>
     */
    private function blockingDataLabels(Store $store): array
    {
        $labels = [];
        if ($store->agents()->exists()) {
            $labels[] = 'equipa';
        }
        if ($store->clients()->exists()) {
            $labels[] = 'clientes';
        }
        if ($store->calendarEvents()->exists()) {
            $labels[] = 'eventos na agenda';
        }
        if ($store->bookings()->exists()) {
            $labels[] = 'marcações online (checkout)';
        }
        if ($store->sales()->exists()) {
            $labels[] = 'vendas';
        }
        if ($store->personalTimeTypes()->exists()) {
            $labels[] = 'tipos de tempo pessoal';
        }
        if ($store->bookingSlotHolds()->exists()) {
            $labels[] = 'reservas temporárias de horário';
        }
        if ($store->bookingAuthCodes()->exists()) {
            $labels[] = 'códigos de autenticação de marcação';
        }

        return $labels;
    }

    private function uniqueStoreSlug(string $base): string
    {
        $slug = $base !== '' ? $base : 'loja';
        $candidate = $slug;
        $n = 0;
        while (Store::query()->where('slug', $candidate)->exists()) {
            $n++;
            $candidate = $slug.'-'.$n;
        }

        return $candidate;
    }

    private function seedDefaultCrmSettings(Store $store): void
    {
        $defaults = [
            \App\Models\CrmSetting::KEY_BOOKING_ONLINE_PAYMENT_REQUIRED => '1',
            \App\Models\CrmSetting::KEY_BOOKING_SLOT_HOLD_MINUTES => '6',
            \App\Models\CrmSetting::KEY_BOOKING_SLOT_INTERVAL_MINUTES => (string) \App\Models\CrmSetting::BOOKING_SLOT_INTERVAL_MINUTES_DEFAULT,
            \App\Models\CrmSetting::KEY_BOOKING_ANY_STAFF_RULE => \App\Models\CrmSetting::BOOKING_ANY_STAFF_RULE_A,
        ];

        foreach ($defaults as $key => $value) {
            \App\Models\CrmSetting::query()->updateOrCreate(
                ['setting_scope' => \App\Models\CrmSetting::storeSettingScope((int) $store->id, $key)],
                [
                    'store_id' => $store->id,
                    'organization_id' => null,
                    'key' => $key,
                    'value' => $value,
                ],
            );
        }
    }
}
