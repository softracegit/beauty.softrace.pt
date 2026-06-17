<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Category;
use App\Models\Note;
use App\Models\Store;
use App\Models\User;
use App\Support\CurrentStore;
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
        $query = Agent::query()->forStore(current_store_id())->orderBy('name');

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

        $agents = $query->with('user')->paginate(30)->withQueryString();

        return view('agentes.index', compact('agents'));
    }

    /**
     * Show the form for creating a new agent.
     */
    public function create()
    {
        $this->authorize('create', Agent::class);
        $categories = Category::forStore(current_store_id())->orderBy('sort_order')
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
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', Rule::exists('services', 'id')->where(fn ($q) => $q->where('store_id', current_store_id()))],
        ]);

        $validated = $this->applySpecializationByRole($validated);
        $validated = $this->normalizeCommission($validated);

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
    public function show(Agent $agente)
    {
        $this->authorize('view', $agente);

        $storeId = current_store_id();
        $agente->load([
            'notes.user',
            'user',
            'services' => function ($q) use ($storeId) {
                $q->where('services.store_id', $storeId)->with('category');
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

        $activities = $agente->activities()
            ->with('causer')
            ->latest()
            ->limit(100)
            ->get();

        $marcacoes = collect();
        $vendas = collect();

        if ($agente->user_id) {
            $marcacoes = \App\Models\CalendarEvent::forStore(current_store_id())->where('user_id', $agente->user_id)
                ->where('event_type', \App\Models\CalendarEvent::TYPE_MARCACAO)
                ->with(['client', 'eventServiceItems.service', 'eventServiceItems.extras.extra'])
                ->orderByDesc('start_at')
                ->limit(100)
                ->get();

            $today = now()->startOfDay();
            $vendas = \App\Models\CalendarEvent::forStore(current_store_id())->where('user_id', $agente->user_id)
                ->where('event_type', \App\Models\CalendarEvent::TYPE_MARCACAO)
                ->where('status', '!=', \App\Models\CalendarEvent::STATUS_CANCELADO)
                ->where('start_at', '<', $today)
                ->with(['client', 'eventServiceItems.service', 'eventServiceItems.extras.extra'])
                ->orderByDesc('start_at')
                ->get()
                ->flatMap(function ($event) {
                    $lines = [];
                    foreach ($event->eventServiceItems as $es) {
                        $lines[] = (object) [
                            'data' => $event->start_at,
                            'cliente' => $event->client?->name ?? '—',
                            'servico' => $es->service?->name ?? '—',
                            'quantidade' => 1,
                            'preco' => (float) $es->price,
                            'tipo' => 'servico',
                        ];
                        foreach ($es->extras ?? [] as $extra) {
                            $lines[] = (object) [
                                'data' => $event->start_at,
                                'cliente' => $event->client?->name ?? '—',
                                'servico' => $extra->extra?->name ?? '—',
                                'quantidade' => 1,
                                'preco' => (float) $extra->price,
                                'tipo' => 'extra',
                            ];
                        }
                    }

                    return $lines;
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
        $agente->load('services');
        $categories = Category::forStore(current_store_id())->orderBy('sort_order')
            ->with(['services' => fn ($q) => $q->orderBy('sort_order')])
            ->get();

        return view('agentes.edit', [
            'agente' => $agente,
            'categories' => $categories,
            'storeHoursLabel' => app(CurrentStore::class)->get()->hoursDisplayLabel(),
        ]);
    }

    /**
     * Update the specified agent.
     */
    public function update(Request $request, Agent $agente)
    {
        $this->authorize('update', $agente);

        $this->prepareCommissionInput($request);

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
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', Rule::exists('services', 'id')->where(fn ($q) => $q->where('store_id', current_store_id()))],
        ]);

        $validated = $this->applySpecializationByRole($validated);
        $validated = $this->normalizeCommission($validated);

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
