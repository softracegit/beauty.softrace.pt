<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\User;
use App\Models\CalendarEvent;
use App\Models\CalendarEventService;
use App\Models\CalendarEventServiceExtra;
use App\Models\Client;
use App\Models\Local;
use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    /**
     * Display a listing of the clients.
     */
    public function index(Request $request)
    {
        $query = Client::query()->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('nif', 'like', "%{$search}%")
                    ->orWhere('locality', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $allowedPerPage = [9, 18, 27, 36, 50];
        $perPage = (int) ($request->get('per_page') ?? $request->cookie('clientes_per_page', 9));
        if (!in_array($perPage, $allowedPerPage)) {
            $perPage = 9;
        }
        if ($request->has('per_page')) {
            cookie()->queue(cookie('clientes_per_page', $perPage, 60 * 24 * 365)); // 1 ano
        }

        $clients = $query->paginate($perPage)->withQueryString();

        // Estatísticas iguais ao Dashboard de Clientes
        $marcacoesBase = CalendarEvent::where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->whereNotNull('client_id');
        $totalClientes = Client::count();
        $totalClientesComMarcacao = (clone $marcacoesBase)->distinct('client_id')->count('client_id');
        $clientesEsteMes = Client::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $clientesComUmaOuMais = (clone $marcacoesBase)->distinct('client_id')->pluck('client_id');
        $clientesComDuasOuMais = CalendarEvent::where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->whereNotNull('client_id')
            ->selectRaw('client_id, count(*) as total')
            ->groupBy('client_id')
            ->havingRaw('count(*) >= 2')
            ->pluck('client_id');
        $taxaRetencao = $clientesComUmaOuMais->count() > 0
            ? round(($clientesComDuasOuMais->count() / $clientesComUmaOuMais->count()) * 100, 1)
            : 0;

        return view('clientes.index', compact(
            'clients',
            'totalClientes',
            'totalClientesComMarcacao',
            'clientesEsteMes',
            'taxaRetencao'
        ));
    }

    /**
     * Show the form for creating a new client.
     */
    public function create()
    {
        $districts = Local::getDistricts();
        $cities = collect();
        $parishes = collect();

        return view('clientes.create', compact(
            'districts',
            'cities',
            'parishes'
        ));
    }

    /**
     * Store a newly created client.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:clients,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'nif' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(array_keys(Client::genders()))],
            'nationality' => ['nullable', 'string', 'max:100'],
            'marital_status' => ['nullable', Rule::in(array_keys(Client::maritalStatuses()))],
            'address' => ['nullable', 'string', 'max:255'],
            'door' => ['nullable', 'string', 'max:10'],
            'floor' => ['nullable', 'string', 'max:10'],
            'side' => ['nullable', 'string', 'max:10'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'locality' => ['nullable', 'string', 'max:255'],
            'id_district' => ['nullable', 'integer'],
            'id_city' => ['nullable', 'integer'],
            'id_parish' => ['nullable', 'integer'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'preferred_schedule' => ['nullable', Rule::in(array_keys(Client::preferredSchedules()))],
            'preferences_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $data = collect($validated)->except('avatar')->all();
        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }
        $cliente = Client::create($data);

        return redirect()->route('clientes.index')
            ->with('success', 'Cliente criado com sucesso.');
    }

    /**
     * Display the specified client.
     */
    public function show(Client $cliente)
    {
        $cliente->load('notes.user');

        $today = now()->startOfDay();
        $marcacoes = $cliente->calendarEvents()
            ->where('event_type', \App\Models\CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', \App\Models\CalendarEvent::STATUS_CANCELADO)
            ->with(['user', 'eventServiceItems.service', 'eventServiceItems.extras.extra'])
            ->orderByDesc('start_at')
            ->limit(100)
            ->get();

        // Vendas: linhas com data, serviço, quantidade, preço (de marcações realizadas)
        $vendas = $cliente->calendarEvents()
            ->where('event_type', \App\Models\CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', \App\Models\CalendarEvent::STATUS_CANCELADO)
            ->where('start_at', '<', $today)
            ->with(['eventServiceItems.service', 'eventServiceItems.extras.extra'])
            ->orderByDesc('start_at')
            ->get()
            ->flatMap(function ($event) {
                $lines = [];
                foreach ($event->eventServiceItems as $es) {
                    $lines[] = (object)[
                        'data' => $event->start_at,
                        'servico' => $es->service?->name ?? '—',
                        'quantidade' => 1,
                        'preco' => (float) $es->price,
                        'tipo' => 'servico',
                    ];
                    foreach ($es->extras ?? [] as $extra) {
                        $lines[] = (object)[
                            'data' => $event->start_at,
                            'servico' => $extra->extra?->name ?? '—',
                            'quantidade' => 1,
                            'preco' => (float) $extra->price,
                            'tipo' => 'extra',
                        ];
                    }
                }
                return $lines;
            });

        $stats = $this->buildClientStats($cliente);
        $agents = Agent::where('status', Agent::STATUS_ACTIVE)
            ->whereHas('user', fn ($q) => $q->where('role', '!=', User::ROLE_ADMIN))
            ->with('user')
            ->orderBy('name')
            ->get();

        return view('clientes.show', compact(
            'cliente',
            'marcacoes',
            'vendas',
            'stats',
            'agents'
        ));
    }

    /**
     * Store a note for the client.
     */
    public function storeNote(Request $request, Client $cliente)
    {
        $validated = $request->validate([
            'note' => ['required', 'string'],
            'type' => ['nullable', 'in:geral,email,chamada,reuniao'],
            'reminder_at' => ['nullable', 'date'],
            'reminder_advance_minutes' => ['nullable', 'integer', 'min:0'],
        ]);

        $cliente->notes()->create([
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
     * Show the form for editing the specified client.
     */
    public function edit(Client $cliente)
    {
        $districts = Local::getDistricts();

        // Buscar dados de localização do cliente se existirem
        $selectedDistrict = null;
        $selectedCity = null;
        $selectedParish = null;
        $cities = collect();
        $parishes = collect();

        if ($cliente->id_district) {
            $selectedDistrict = $cliente->id_district;
            $cities = Local::getCitiesByDistrict($cliente->id_district);
            
            if ($cliente->id_city) {
                $selectedCity = $cliente->id_city;
                $parishes = Local::getParishesByCity($cliente->id_city);
                
                if ($cliente->id_parish) {
                    $selectedParish = $cliente->id_parish;
                }
            }
        }

        return view('clientes.edit', compact(
            'cliente',
            'districts',
            'selectedDistrict',
            'selectedCity',
            'selectedParish',
            'cities',
            'parishes'
        ));
    }

    /**
     * Update the specified client.
     */
    public function update(Request $request, Client $cliente)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('clients', 'email')->ignore($cliente->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'nif' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(array_keys(Client::genders()))],
            'nationality' => ['nullable', 'string', 'max:100'],
            'marital_status' => ['nullable', Rule::in(array_keys(Client::maritalStatuses()))],
            'address' => ['nullable', 'string', 'max:255'],
            'door' => ['nullable', 'string', 'max:10'],
            'floor' => ['nullable', 'string', 'max:10'],
            'side' => ['nullable', 'string', 'max:10'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'locality' => ['nullable', 'string', 'max:255'],
            'id_district' => ['nullable', 'integer'],
            'id_city' => ['nullable', 'integer'],
            'id_parish' => ['nullable', 'integer'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'preferred_schedule' => ['nullable', Rule::in(array_keys(Client::preferredSchedules()))],
            'preferences_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($request->hasFile('avatar')) {
            if ($cliente->avatar && Storage::disk('public')->exists($cliente->avatar)) {
                Storage::disk('public')->delete($cliente->avatar);
            }
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        } else {
            unset($validated['avatar']);
        }
        $cliente->update($validated);

        return redirect()->route('clientes.show', $cliente)
            ->with('success', 'Cliente atualizado com sucesso.');
    }

    /**
     * Remove the specified client.
     */
    public function destroy(Client $cliente)
    {
        $cliente->delete();

        return redirect()->route('clientes.index')
            ->with('success', 'Cliente removido com sucesso.');
    }

    /**
     * Estatísticas do cliente (receita por mês, top serviços, técnico preferido, padrão agendamento, retenção).
     */
    private function buildClientStats(Client $cliente): object
    {
        $marcacoesBase = $cliente->calendarEvents()
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO);

        $totalMarcacoes = $marcacoesBase->count();
        $clienteRecorrente = $totalMarcacoes >= 2;

        $receitaPorMes = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $start = $date->copy()->startOfMonth();
            $end = $date->copy()->endOfMonth();
            $eventIds = (clone $marcacoesBase)
                ->whereBetween('start_at', [$start, $end])
                ->pluck('id');
            $receita = 0;
            if ($eventIds->isNotEmpty()) {
                $servicos = CalendarEventService::whereIn('calendar_event_id', $eventIds)->sum('price');
                $cesIds = CalendarEventService::whereIn('calendar_event_id', $eventIds)->pluck('id');
                $extras = CalendarEventServiceExtra::whereIn('calendar_event_service_id', $cesIds)->sum('price');
                $receita = (float) $servicos + (float) $extras;
            }
            $receitaPorMes[] = [
                'month' => $date->locale('pt_PT')->translatedFormat('M Y'),
                'revenue' => round($receita, 2),
            ];
        }

        $topServicos = CalendarEventService::query()
            ->join('calendar_events', 'calendar_event_services.calendar_event_id', '=', 'calendar_events.id')
            ->join('services', 'calendar_event_services.service_id', '=', 'services.id')
            ->where('calendar_events.client_id', $cliente->id)
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('calendar_events.status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->selectRaw('services.name as service_name, count(*) as total')
            ->groupBy('services.id', 'services.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $tecnicoPreferidoRow = CalendarEvent::where('client_id', $cliente->id)
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->whereNotNull('user_id')
            ->selectRaw('user_id, count(*) as total')
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->first();
        $tecnicoPreferidoNome = $tecnicoPreferidoRow
            ? \App\Models\User::find($tecnicoPreferidoRow->user_id)?->name ?? '—'
            : null;

        $porDiaSemana = [];
        $diasNomes = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
        foreach (range(1, 7) as $d) {
            $porDiaSemana[] = [
                'nome' => $diasNomes[$d - 1],
                'total' => (clone $marcacoesBase)->whereRaw('DAYOFWEEK(start_at) = ?', [$d])->count(),
            ];
        }

        $porHora = [];
        for ($h = 0; $h < 24; $h++) {
            $porHora[$h] = (clone $marcacoesBase)->whereRaw('HOUR(start_at) = ?', [$h])->count();
        }

        $today = now()->startOfDay();
        $ultimaVisita = (clone $marcacoesBase)->where('start_at', '<', $today)->orderByDesc('start_at')->first();
        $marcacoesFuturas = (clone $marcacoesBase)->where('start_at', '>=', $today)->count();

        $intervaloMedioDias = null;
        if ($totalMarcacoes >= 2) {
            $datas = (clone $marcacoesBase)->orderBy('start_at')->pluck('start_at')
                ->map(fn ($d) => $d->copy()->startOfDay()->timestamp)->values()->all();
            $somas = 0;
            $n = 0;
            for ($i = 1; $i < count($datas); $i++) {
                $somas += ($datas[$i] - $datas[$i - 1]) / 86400;
                $n++;
            }
            $intervaloMedioDias = $n > 0 ? round($somas / $n, 1) : null;
        }

        return (object) [
            'clienteRecorrente' => $clienteRecorrente,
            'totalMarcacoes' => $totalMarcacoes,
            'receitaPorMes' => $receitaPorMes,
            'topServicos' => $topServicos,
            'tecnicoPreferido' => $tecnicoPreferidoNome,
            'porDiaSemana' => $porDiaSemana,
            'porHora' => $porHora,
            'intervaloMedioDias' => $intervaloMedioDias,
            'ultimaVisita' => $ultimaVisita?->start_at,
            'marcacoesFuturas' => $marcacoesFuturas,
        ];
    }

}
