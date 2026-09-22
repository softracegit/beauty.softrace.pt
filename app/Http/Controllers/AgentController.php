<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetCurrentStore;
use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\Category;
use App\Models\Note;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\MigrateAgentToStoreService;
use App\Services\VendasReportService;
use App\Support\ActivityLogUserTimeline;
use App\Support\CurrentStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AgentController extends Controller
{
    /**
     * Display a listing of the agents.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Agent::class);

        $user = auth()->user();
        $filter = \App\Support\StoreContextPreference::resolveEquipaFilter($user, $request);
        if ($filter['store_id'] !== null) {
            \App\Support\StoreContextPreference::persist($request, $filter['store_id']);
        }

        $query = Agent::query()
            ->whereIn('store_id', $filter['store_ids'])
            ->with(['user', 'store'])
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->search;
            $specSlugsByLabel = collect(Agent::specializations())
                ->filter(fn (string $label) => stripos($label, $search) !== false)
                ->keys()
                ->all();
            $query->where(function ($q) use ($search, $specSlugsByLabel) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('nif', 'like', "%{$search}%")
                    ->orWhere('locality', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('specialization', 'like', "%{$search}%");
                if ($specSlugsByLabel !== []) {
                    $q->orWhereIn('specialization', $specSlugsByLabel);
                }
                $q->orWhereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('email', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            });
        }

        $statusFilter = (string) $request->input('status', '');
        if ($statusFilter === 'all') {
            // Sem filtro de estado.
        } elseif ($statusFilter === Agent::STATUS_INACTIVE) {
            $query->where('status', Agent::STATUS_INACTIVE);
        } else {
            $query->where('status', '!=', Agent::STATUS_INACTIVE);
        }

        $agents = $query->paginate(30)->withQueryString();

        $statsBase = Agent::query()->whereIn('store_id', $filter['store_ids']);
        $totalAgentes = (clone $statsBase)->count();
        $activeCount = (clone $statsBase)->where('status', Agent::STATUS_ACTIVE)->count();
        $inactiveCount = (clone $statsBase)->where('status', Agent::STATUS_INACTIVE)->count();
        $onLeaveCount = (clone $statsBase)->where('status', Agent::STATUS_ON_LEAVE)->count();

        $migrateStores = collect();
        $migrateCandidatesByAgent = [];
        $orgId = $user?->organization_id;
        if ($user?->isAdmin() && $orgId) {
            $migrateStores = Store::query()
                ->where('organization_id', $orgId)
                ->orderBy('name')
                ->get(['id', 'name']);

            $storePrestadores = Agent::query()
                ->whereIn('store_id', $filter['store_ids'])
                ->whereHas('user', fn ($q) => $q->where('role', User::ROLE_PRESTADOR))
                ->with('user:id,role')
                ->orderBy('name')
                ->get(['id', 'name', 'user_id', 'store_id']);

            foreach ($storePrestadores as $prestador) {
                $sameStore = $storePrestadores->where('store_id', $prestador->store_id);
                $migrateCandidatesByAgent[$prestador->id] = $sameStore
                    ->filter(fn (Agent $other) => (int) $other->id !== (int) $prestador->id)
                    ->map(fn (Agent $other) => ['id' => $other->id, 'name' => $other->name])
                    ->values()
                    ->all();
            }
        }

        return view('agentes.index', [
            'agents' => $agents,
            'migrateStores' => $migrateStores,
            'migrateCandidatesByAgent' => $migrateCandidatesByAgent,
            'equipaStoreFilter' => $filter,
            'totalAgentes' => $totalAgentes,
            'activeCount' => $activeCount,
            'inactiveCount' => $inactiveCount,
            'onLeaveCount' => $onLeaveCount,
            'showStoreColumn' => count($filter['store_ids']) > 1 || $filter['scope'] === \App\Support\StoreContextPreference::SCOPE_ALL,
        ]);
    }

    /**
     * Show the form for creating a new agent.
     */
    public function create()
    {
        $this->authorize('create', Agent::class);
        $categories = Category::forOrganization(current_organization_id())->orderBy('sort_order')
            ->with(['services' => fn ($q) => $q->orderBy('sort_order')])
            ->get();

        return view('agentes.create', [
            'categories' => $categories,
            'storeHoursLabel' => app(CurrentStore::class)->get()->hoursDisplayLabel(),
        ]);
    }

    /**
     * Store a newly created agent.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Agent::class);

        $this->prepareCommissionInput($request);
        $this->prepareBookingSlugInput($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(array_keys(User::staffAssignableRoles()))],
            'phone' => ['nullable', 'string', 'max:50'],
            'nif' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(array_keys(Agent::genders()))],
            'nationality' => ['nullable', 'string', 'max:100'],
            'marital_status' => ['nullable', Rule::in(array_keys(Agent::maritalStatuses()))],
            'address' => ['nullable', 'string', 'max:255'],
            'door' => ['nullable', 'string', 'max:10'],
            'floor' => ['nullable', 'string', 'max:10'],
            'side' => ['nullable', 'string', 'max:10'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'locality' => ['nullable', 'string', 'max:255'],
            'specialization' => $this->specializationRules($request),
            'commission_unit' => ['nullable', Rule::in([Agent::COMMISSION_UNIT_PERCENT, Agent::COMMISSION_UNIT_EURO])],
            'commission_rate' => $this->commissionRateRules($request),
            'status' => ['required', Rule::in(['active', 'inactive', 'on_leave'])],
            'color' => ['nullable', 'string', 'max:20'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'booking_slug' => $this->bookingSlugRules($request, null),
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', Rule::exists('services', 'id')->where(fn ($q) => $q->where('organization_id', current_organization_id()))],
        ]);

        $validated = $this->applySpecializationByRole($validated);
        $validated = $this->normalizeCommission($validated);
        $validated = $this->normalizeBookingSlugInput($validated);

        $storeId = current_store_id();
        $organizationId = Store::query()->whereKey($storeId)->value('organization_id');

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'organization_id' => $organizationId,
        ]);

        $agentData = collect($validated)->except(['email', 'password', 'password_confirmation', 'role', 'avatar', 'service_ids'])->all();
        $agentData['user_id'] = $user->id;
        $agentData['store_id'] = $storeId;
        $agentData['weekly_schedule'] = $this->validatedWeeklySchedule($request);

        if ($request->hasFile('avatar')) {
            $agentData['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $agent = Agent::create($agentData);
        $user->stores()->sync([$storeId]);
        $agent->services()->sync($request->input('service_ids', []));

        return redirect()->route('equipa.index')
            ->with('success', 'Membro criado com sucesso.');
    }

    /**
     * Display the specified agent.
     */
    public function show(Agent $agente, VendasReportService $vendasReportService)
    {
        $this->authorize('view', $agente);
        $this->alignStoreContextToAgent($agente);

        $storeId = current_store_id();
        $organizationId = current_organization_id();
        $agente->load([
            'notes.user',
            'user',
            'services' => function ($q) use ($organizationId) {
                $q->where('services.organization_id', $organizationId)->with('category');
            },
        ]);

        $servicesByCategory = $agente->services
            ->sort(function (\App\Models\Service $a, \App\Models\Service $b): int {
                $orderA = $a->category?->sort_order ?? 99999;
                $orderB = $b->category?->sort_order ?? 99999;
                if ($orderA !== $orderB) {
                    return $orderA <=> $orderB;
                }
                $idA = $a->category_id ?? 0;
                $idB = $b->category_id ?? 0;
                if ($idA !== $idB) {
                    return $idA <=> $idB;
                }
                $sA = $a->sort_order ?? 0;
                $sB = $b->sort_order ?? 0;
                if ($sA !== $sB) {
                    return $sA <=> $sB;
                }

                return strcmp($a->name, $b->name);
            })
            ->groupBy(fn (\App\Models\Service $s) => (string) ($s->category_id ?? '0'));

        $activities = ActivityLogUserTimeline::paginateForAgent($agente);

        $marcacoes = collect();
        $vendas = collect();

        if ($agente->user_id) {
            $marcacoes = \App\Models\CalendarEvent::forStore(current_store_id())->where('user_id', $agente->user_id)
                ->where('event_type', \App\Models\CalendarEvent::TYPE_MARCACAO)
                ->with(['client', 'eventServiceItems.service', 'eventServiceItems.extras.extra'])
                ->orderByDesc('start_at')
                ->limit(100)
                ->get();

            $sales = Sale::query()
                ->where('store_id', current_store_id())
                ->where('status', Sale::STATUS_PAGO)
                ->whereIn('invoice_status', [Sale::INVOICE_STATUS_FATURADO, Sale::INVOICE_STATUS_RASCUNHO])
                ->whereHas('calendarEvent', function (Builder $cq): void {
                    $cq->where('store_id', current_store_id())
                        ->where('event_type', CalendarEvent::TYPE_MARCACAO)
                        ->where('status', '!=', CalendarEvent::STATUS_CANCELADO);
                })
                ->where(function (Builder $outer) use ($agente): void {
                    $userId = (int) $agente->user_id;
                    $outer->whereHas('calendarEvent', fn (Builder $cq): Builder => $cq
                        ->where('store_id', current_store_id())
                        ->where('user_id', $userId))
                        ->orWhereHas('items.calendarEventService.event', fn (Builder $cq): Builder => $cq
                            ->where('store_id', current_store_id())
                            ->where('user_id', $userId));
                })
                ->with([
                    'client',
                    'calendarEvent.user',
                    'items.service',
                    'items.extra',
                    'items.calendarEventService.event.user',
                    'settledEvents',
                ])
                ->orderByDesc('data_emissao')
                ->orderByDesc('id')
                ->limit(300)
                ->get();

            $vendas = $vendasReportService
                ->resumoCollection($sales, null, (string) $agente->user_id)
                ->map(function (object $linha): object {
                    $servico = trim((string) ($linha->servico_nomes ?? $linha->servico ?? '—'));

                    return (object) [
                        'data' => $linha->data,
                        'cliente' => $linha->cliente,
                        'servico' => $servico !== '' ? $servico : '—',
                        'quantidade' => (int) $linha->quantidade,
                        'preco' => (float) $linha->valor,
                        'tipo' => 'servico',
                    ];
                });
        }

        return view('agentes.show', [
            'agente' => $agente,
            'activities' => $activities,
            'marcacoes' => $marcacoes,
            'vendas' => $vendas,
            'servicesByCategory' => $servicesByCategory,
            'storeHoursLabel' => app(CurrentStore::class)->get()->hoursDisplayLabel(),
        ]);
    }

    /**
     * Store a note for the agent.
     */
    public function storeNote(Request $request, Agent $agente)
    {
        $this->authorize('update', $agente);
        $this->alignStoreContextToAgent($agente);

        $validated = $request->validate([
            'note' => ['required', 'string'],
            'type' => ['nullable', 'in:geral,email,chamada,reuniao'],
            'reminder_at' => ['nullable', 'date'],
            'reminder_advance_minutes' => ['nullable', 'integer', 'min:0'],
        ]);

        $agente->notes()->create([
            'user_id' => auth()->id(),
            'type' => $validated['type'] ?? Note::TYPE_GERAL,
            'note' => $validated['note'],
            'reminder_at' => $validated['reminder_at'] ?? null,
            'reminder_advance_minutes' => $validated['reminder_advance_minutes'] ?? 15,
            'reminder_sent' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Nota adicionada com sucesso.',
        ]);
    }

    /**
     * Show the form for editing the specified agent.
     */
    public function edit(Agent $agente)
    {
        $this->authorize('update', $agente);
        $this->alignStoreContextToAgent($agente);
        $agente->load('services');
        $categories = Category::forOrganization(current_organization_id())->orderBy('sort_order')
            ->with(['services' => fn ($q) => $q->orderBy('sort_order')])
            ->get();

        return view('agentes.edit', [
            'agente' => $agente,
            'categories' => $categories,
            'storeHoursLabel' => app(CurrentStore::class)->get()->hoursDisplayLabel(),
            'bookingStoreSlug' => app(CurrentStore::class)->get()->slug,
        ]);
    }

    /**
     * Update the specified agent.
     */
    public function update(Request $request, Agent $agente)
    {
        $this->authorize('update', $agente);
        $this->alignStoreContextToAgent($agente);

        $this->prepareCommissionInput($request);
        $this->prepareBookingSlugInput($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($agente->user_id)],
            'role' => ['required', Rule::in(array_keys(User::staffAssignableRoles()))],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:50'],
            'nif' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(array_keys(Agent::genders()))],
            'nationality' => ['nullable', 'string', 'max:100'],
            'marital_status' => ['nullable', Rule::in(array_keys(Agent::maritalStatuses()))],
            'address' => ['nullable', 'string', 'max:255'],
            'door' => ['nullable', 'string', 'max:10'],
            'floor' => ['nullable', 'string', 'max:10'],
            'side' => ['nullable', 'string', 'max:10'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'locality' => ['nullable', 'string', 'max:255'],
            'specialization' => $this->specializationRules($request),
            'commission_unit' => ['nullable', Rule::in([Agent::COMMISSION_UNIT_PERCENT, Agent::COMMISSION_UNIT_EURO])],
            'commission_rate' => $this->commissionRateRules($request),
            'status' => ['required', Rule::in(['active', 'inactive', 'on_leave'])],
            'color' => ['nullable', 'string', 'max:20'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'booking_slug' => $this->bookingSlugRules($request, $agente->id, (int) $agente->store_id),
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', Rule::exists('services', 'id')->where(fn ($q) => $q->where('organization_id', current_organization_id()))],
        ]);

        $validated = $this->applySpecializationByRole($validated);
        $validated = $this->normalizeCommission($validated);
        $validated = $this->normalizeBookingSlugInput($validated);

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            if ($agente->avatar && Storage::disk('public')->exists($agente->avatar)) {
                Storage::disk('public')->delete($agente->avatar);
            }
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        } else {
            unset($validated['avatar']);
        }

        // Update agent data
        $agentData = $validated;
        unset($agentData['email'], $agentData['role'], $agentData['password']);
        $agentData['weekly_schedule'] = $this->validatedWeeklySchedule($request);
        $agente->update($agentData);

        // Update user data
        if ($agente->user) {
            $userData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $validated['role'],
            ];

            if (! empty($validated['password'])) {
                $userData['password'] = Hash::make($validated['password']);
            }

            $agente->user->update($userData);
        }

        $agente->services()->sync($request->input('service_ids', []));

        return redirect()->route('equipa.show', $agente)
            ->with('success', 'Membro atualizado com sucesso.');
    }

    /**
     * Remove the specified agent.
     */
    public function destroy(Agent $agente)
    {
        $this->authorize('delete', $agente);
        $this->alignStoreContextToAgent($agente);

        // O user será removido automaticamente devido ao cascadeOnDelete
        $agente->delete();

        return redirect()->route('equipa.index')
            ->with('success', 'Membro removido com sucesso.');
    }

    /**
     * @return array<string, array{enabled: bool, start: ?string, end: ?string}>|null
     */
    private function validatedWeeklySchedule(Request $request): ?array
    {
        $raw = $request->input('weekly_schedule');
        if (! is_array($raw)) {
            return null;
        }

        $timePattern = '/^([01]\d|2[0-3]):(00|15|30|45)$/';
        $out = [];

        foreach (Agent::WEEKDAY_KEYS as $day) {
            $dayIn = $raw[$day] ?? [];
            $enabled = filter_var($dayIn['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if (! $enabled) {
                $out[$day] = ['enabled' => false, 'start' => null, 'end' => null];

                continue;
            }
            $start = $dayIn['start'] ?? '09:00';
            $end = $dayIn['end'] ?? '20:00';
            if (! is_string($start) || ! is_string($end) || ! preg_match($timePattern, $start) || ! preg_match($timePattern, $end)) {
                throw ValidationException::withMessages([
                    "weekly_schedule.{$day}" => 'Horário inválido. Use intervalos de 15 minutos (00:00–23:45).',
                ]);
            }
            $smin = Agent::timeStringToMinutes($start);
            $emin = Agent::timeStringToMinutes($end);
            if ($smin >= $emin) {
                throw ValidationException::withMessages([
                    "weekly_schedule.{$day}" => 'A hora de início deve ser anterior à hora de fim.',
                ]);
            }
            $out[$day] = ['enabled' => true, 'start' => $start, 'end' => $end];
        }

        return $out;
    }

    /** @return array<int, \Illuminate\Contracts\Validation\ValidationRule|string> */
    private function specializationRules(Request $request): array
    {
        return [
            'nullable',
            function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                if (! in_array($request->input('role'), User::rolesWithSpecialization(), true)) {
                    return;
                }
                if ($value === null || $value === '') {
                    return;
                }
                if (! array_key_exists((string) $value, Agent::specializations())) {
                    $fail('A especialização selecionada é inválida.');
                }
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function applySpecializationByRole(array $validated): array
    {
        if (! in_array($validated['role'], User::rolesWithSpecialization(), true)) {
            $validated['specialization'] = null;
        } else {
            $validated['specialization'] = $validated['specialization'] !== '' && $validated['specialization'] !== null
                ? (string) $validated['specialization']
                : null;
        }

        return $validated;
    }

    private function prepareCommissionInput(Request $request): void
    {
        if ($request->input('commission_rate') === '') {
            $request->merge(['commission_rate' => null]);
        }
    }

    private function prepareBookingSlugInput(Request $request): void
    {
        if (! $request->has('booking_slug')) {
            return;
        }

        $raw = $request->input('booking_slug');
        if ($raw === null || $raw === '') {
            $request->merge(['booking_slug' => null]);

            return;
        }

        $request->merge(['booking_slug' => Agent::normalizeBookingSlug((string) $raw)]);
    }

    /** @return array<int, \Illuminate\Contracts\Validation\ValidationRule|string> */
    private function bookingSlugRules(Request $request, ?int $ignoreAgentId, ?int $storeId = null): array
    {
        $storeId = $storeId ?? current_store_id();

        return [
            'nullable',
            'string',
            'max:80',
            'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            Rule::notIn(Agent::reservedBookingSlugs()),
            Rule::unique('agents', 'booking_slug')
                ->where(fn ($q) => $q->where('store_id', $storeId))
                ->ignore($ignoreAgentId),
        ];
    }

    public function migrateStoreForm(Agent $agente): \Illuminate\View\View
    {
        $this->authorize('migrateStore', $agente);
        $this->alignStoreContextToAgent($agente);

        $agente->loadMissing(['store', 'user']);
        $stores = Store::query()
            ->where('organization_id', auth()->user()->organization_id)
            ->where('id', '!=', $agente->store_id)
            ->orderBy('name')
            ->get();

        $takeOverCandidates = Agent::query()
            ->forStore((int) $agente->store_id)
            ->where('id', '!=', $agente->id)
            ->whereHas('user', fn ($q) => $q->where('role', User::ROLE_PRESTADOR))
            ->orderBy('name')
            ->get(['id', 'name']);

        $futureCount = app(MigrateAgentToStoreService::class)->futureAppointmentsCount($agente);

        return view('agentes.migrate-store', [
            'agente' => $agente,
            'stores' => $stores,
            'takeOverCandidates' => $takeOverCandidates,
            'futureCount' => $futureCount,
        ]);
    }

    public function migrateStore(Request $request, Agent $agente, MigrateAgentToStoreService $migrator): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('migrateStore', $agente);
        $this->alignStoreContextToAgent($agente);

        $validated = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'future_mode' => ['required', Rule::in([
                MigrateAgentToStoreService::MODE_MIGRATE_FUTURE,
                MigrateAgentToStoreService::MODE_REASSIGN,
            ])],
            'take_over_agent_id' => ['nullable', 'integer', 'exists:agents,id'],
        ]);

        $target = Store::query()->findOrFail($validated['store_id']);
        if ((int) $target->organization_id !== (int) auth()->user()->organization_id) {
            abort(403);
        }

        $mode = $validated['future_mode'];
        $takeOver = null;
        if ($mode === MigrateAgentToStoreService::MODE_REASSIGN && ! empty($validated['take_over_agent_id'])) {
            $takeOver = Agent::query()->findOrFail((int) $validated['take_over_agent_id']);
        }

        $conflicts = $migrator->conflictMessages($agente, $target, $mode, $takeOver);
        if ($conflicts !== []) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Não é possível migrar: verifique as opções e o catálogo da loja destino.')
                ->with('migrate_conflicts', $conflicts);
        }

        try {
            $migrator->migrate($agente, $target, $mode, $takeOver);
        } catch (\RuntimeException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }

        if ($mode === MigrateAgentToStoreService::MODE_MIGRATE_FUTURE) {
            $request->session()->put(\App\Http\Middleware\SetCurrentStore::SESSION_KEY, $target->id);
            $msg = 'Membro transferido para '.$target->name.'. As marcações futuras foram com ele.';
        } else {
            $msg = 'Membro transferido para '.$target->name.'.'
                .($takeOver ? ' As marcações futuras ficaram com '.$takeOver->name.'.' : '');
        }

        return redirect()
            ->route('equipa.index')
            ->with('success', $msg);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeBookingSlugInput(array $validated): array
    {
        $raw = $validated['booking_slug'] ?? null;
        if ($raw === null || $raw === '') {
            $validated['booking_slug'] = null;

            return $validated;
        }

        $validated['booking_slug'] = Agent::normalizeBookingSlug((string) $raw);

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeCommission(array $validated): array
    {
        $rate = $validated['commission_rate'] ?? null;
        if ($rate === null || $rate === '') {
            $validated['commission_rate'] = null;
            $validated['commission_unit'] = null;

            return $validated;
        }

        $unit = $validated['commission_unit'] ?? Agent::COMMISSION_UNIT_PERCENT;
        if (! in_array($unit, [Agent::COMMISSION_UNIT_PERCENT, Agent::COMMISSION_UNIT_EURO], true)) {
            $unit = Agent::COMMISSION_UNIT_PERCENT;
        }
        $validated['commission_unit'] = $unit;
        $validated['commission_rate'] = round((float) $rate, 2);

        return $validated;
    }

    /**
     * Após abrir um membro de outra loja (lista «Todas»), alinhar CurrentStore/sessão
     * para marcações, vendas e labels usarem a loja correcta. Não altera o cookie «todas».
     */
    private function alignStoreContextToAgent(Agent $agente): void
    {
        $storeId = (int) $agente->store_id;
        if ($storeId <= 0 || $storeId === (int) current_store_id()) {
            return;
        }

        $store = Store::query()->find($storeId);
        if (! $store) {
            return;
        }

        app(CurrentStore::class)->set($store);
        request()->session()->put(SetCurrentStore::SESSION_KEY, $storeId);
    }

    /** @return array<int, \Illuminate\Contracts\Validation\ValidationRule|string> */
    private function commissionRateRules(Request $request): array
    {
        return [
            'nullable',
            'numeric',
            'min:0',
            function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                if ($value === null || $value === '') {
                    return;
                }
                $unit = $request->input('commission_unit') ?: Agent::COMMISSION_UNIT_PERCENT;
                if ($unit === Agent::COMMISSION_UNIT_PERCENT && (float) $value > 100) {
                    $fail('A percentagem de comissão não pode ser superior a 100%.');
                }
            },
        ];
    }
}
