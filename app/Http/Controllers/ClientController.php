<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\CalendarEventService;
use App\Models\Client;
use App\Models\ClientTag;
use App\Models\Local;
use App\Models\Note;
use App\Models\Sale;
use App\Models\User;
use App\Rules\UniqueClientPhone;
use App\Rules\ClientFullName;
use App\Services\VendasReportService;
use App\Support\ActivityLogQuery;
use App\Support\DateTimeDisplay;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientController extends Controller
{
    public function __construct(
        private readonly VendasReportService $vendasReportService,
    ) {}

    /**
     * Display a listing of the clients.
     */
    public function index(Request $request)
    {
        $query = $this->clientsFilteredQuery($request);

        $allowedPerPage = [9, 18, 27, 36, 50];
        $perPage = (int) ($request->get('per_page') ?? $request->cookie('clientes_per_page', 9));
        if (! in_array($perPage, $allowedPerPage)) {
            $perPage = 9;
        }
        if ($request->has('per_page')) {
            cookie()->queue(cookie('clientes_per_page', $perPage, 60 * 24 * 365)); // 1 ano
        }

        $clients = $query->with('tags')->paginate($perPage)->withQueryString();

        $clientTags = ClientTag::query()
            ->forStore(current_store_id())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Estatísticas iguais ao Dashboard de Clientes
        $marcacoesBase = CalendarEvent::forStore(current_store_id())->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->whereNotNull('client_id');
        $totalClientes = Client::forStore(current_store_id())->count();
        $totalClientesComMarcacao = (clone $marcacoesBase)->distinct('client_id')->count('client_id');
        $clientesEsteMes = Client::forStore(current_store_id())->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $clientesComUmaOuMais = (clone $marcacoesBase)->distinct('client_id')->pluck('client_id');
        $clientesComDuasOuMais = CalendarEvent::forStore(current_store_id())->where('event_type', CalendarEvent::TYPE_MARCACAO)
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
            'clientTags',
            'totalClientes',
            'totalClientesComMarcacao',
            'clientesEsteMes',
            'taxaRetencao'
        ));
    }

    public function indexExport(Request $request): StreamedResponse
    {
        $clients = $this->clientsFilteredQuery($request)->with('tags')->get();

        $districtNames = $this->localNamesById(
            $clients->pluck('id_district')->filter()->unique()->all(),
            'id_district',
            'district'
        );
        $cityNames = $this->localNamesById(
            $clients->pluck('id_city')->filter()->unique()->all(),
            'id_city',
            'city'
        );
        $parishNames = $this->localNamesById(
            $clients->pluck('id_parish')->filter()->unique()->all(),
            'id_parish',
            'parish'
        );

        $genders = Client::genders();
        $maritalStatuses = Client::maritalStatuses();
        $schedules = Client::preferredSchedules();
        $types = Client::types();
        $yesNo = static fn (?bool $value): string => $value ? 'Sim' : 'Não';

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Clientes');

        $headers = [
            'Código',
            'Nome',
            'Email',
            'Telefone',
            'NIF',
            'Data de nascimento',
            'Idade',
            'Género',
            'Nacionalidade',
            'Estado civil',
            'Origem',
            'Profissão',
            'Etiquetas',
            'Morada',
            'Porta',
            'Andar',
            'Lado',
            'Código postal',
            'Localidade',
            'Distrito',
            'Concelho',
            'Freguesia',
            'Horário preferido',
            'Observações das preferências',
            'Saldo carteira (€)',
            'Notificar email (atualizações)',
            'Notificar email (lembretes)',
            'Notificar SMS (lembretes)',
            'Termos aceites em',
            'Telemóvel verificado em',
            'Tipo',
            'Registado em',
            'Última atualização',
        ];
        $sheet->fromArray([$headers], null, 'A1');

        $rowIndex = 2;
        foreach ($clients as $c) {
            $storeId = $c->store_id ? (int) $c->store_id : null;
            $walletCents = (int) ($c->wallet_balance_cents ?? 0);

            $sheet->fromArray([
                [
                    $c->client_id,
                    $c->name ?? '',
                    $c->email ?? '',
                    $c->formatted_phone ?? '',
                    $c->nif ?? '',
                    $c->birth_date?->format('d/m/Y') ?? '',
                    $c->age ?? '',
                    $genders[$c->gender] ?? ($c->gender ?? ''),
                    $c->nationality ?? '',
                    $maritalStatuses[$c->marital_status] ?? ($c->marital_status ?? ''),
                    $c->origem ?? '',
                    $c->profissao ?? '',
                    $c->tags->pluck('name')->filter()->sort()->values()->implode(', '),
                    $c->address ?? '',
                    $c->door ?? '',
                    $c->floor ?? '',
                    $c->side ?? '',
                    $c->postal_code ?? '',
                    $c->locality ?? '',
                    $districtNames[(int) $c->id_district] ?? '',
                    $cityNames[(int) $c->id_city] ?? '',
                    $parishNames[(int) $c->id_parish] ?? '',
                    $schedules[$c->preferred_schedule] ?? ($c->preferred_schedule ?? ''),
                    $c->preferences_notes ?? '',
                    number_format($walletCents / 100, 2, ',', ' '),
                    $yesNo((bool) $c->notify_email_booking_updates),
                    $yesNo((bool) $c->notify_email_booking_reminders),
                    $yesNo((bool) $c->notify_sms_booking_reminders),
                    DateTimeDisplay::formatInstant($c->terms_accepted_at, $storeId, 'd/m/Y H:i', ''),
                    DateTimeDisplay::formatInstant($c->phone_verified_at, $storeId, 'd/m/Y H:i', ''),
                    $types[$c->type] ?? ($c->type ?? ''),
                    DateTimeDisplay::formatInstant($c->created_at, $storeId, 'd/m/Y H:i', ''),
                    DateTimeDisplay::formatInstant($c->updated_at, $storeId, 'd/m/Y H:i', ''),
                ],
            ], null, 'A'.$rowIndex);
            $rowIndex++;
        }

        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle('A1:'.$lastCol.'1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        foreach (range(1, count($headers)) as $colIndex) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIndex))->setAutoSize(true);
        }

        $filename = 'clientes_'.now()->format('Y-m-d_His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  list<int|string>  $ids
     * @return array<int, string>
     */
    private function localNamesById(array $ids, string $idColumn, string $nameColumn): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }

        return Local::query()
            ->select($idColumn, $nameColumn)
            ->whereIn($idColumn, $ids)
            ->whereNotNull($nameColumn)
            ->distinct()
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->{$idColumn} => (string) $row->{$nameColumn}])
            ->all();
    }

    public function indexPdf(Request $request)
    {
        $clients = $this->clientsFilteredQuery($request)->get();

        $filtrosLinhas = [];
        if ($request->filled('search')) {
            $filtrosLinhas[] = 'Pesquisa: '.$request->get('search');
        }

        $pdf = Pdf::loadView('clientes.pdf.list', [
            'clients' => $clients,
            'filtrosLinhas' => $filtrosLinhas,
            'appName' => config('app.name'),
            'total' => $clients->count(),
        ])->setPaper('a4', 'landscape');

        $filename = 'clientes_'.now()->format('Y-m-d_His').'.pdf';

        return $pdf->stream($filename);
    }

    /**
     * Query da listagem de clientes com o mesmo filtro de pesquisa (GET search).
     */
    private function clientsFilteredQuery(Request $request): Builder
    {
        $allowedSorts = [
            'name' => 'name',
            'created_at' => 'created_at',
        ];
        $sortBy = (string) $request->get('sort_by', 'created_at');
        if (! array_key_exists($sortBy, $allowedSorts)) {
            $sortBy = 'created_at';
        }
        $sortDir = strtolower((string) $request->get('sort_dir', $sortBy === 'created_at' ? 'desc' : 'asc'));
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = $sortBy === 'created_at' ? 'desc' : 'asc';
        }

        $query = Client::query()->forStore(current_store_id())->orderBy($allowedSorts[$sortBy], $sortDir);
        if ($sortBy !== 'created_at') {
            // Desempate consistente: mais recentes primeiro.
            $query->orderByDesc('created_at');
        }

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

        if ($request->filled('tag')) {
            $tagId = (int) $request->tag;
            if ($tagId > 0) {
                $query->whereHas('tags', fn ($q) => $q->where('client_tags.id', $tagId));
            }
        }

        return $query;
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
            'name' => ['required', 'string', 'max:255', new ClientFullName],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('clients', 'email')->where(fn ($q) => $q->where('store_id', current_store_id()))],
            'phone' => ['required', 'string', 'max:50', new UniqueClientPhone],
            'nif' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(array_keys(Client::genders()))],
            'nationality' => ['nullable', 'string', 'max:100'],
            'marital_status' => ['nullable', Rule::in(array_keys(Client::maritalStatuses()))],
            'origem' => ['nullable', 'string', 'max:255'],
            'profissao' => ['nullable', 'string', 'max:255'],
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
        ], $this->clientContactValidationMessages());

        $data = collect($validated)->except('avatar')->all();
        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        try {
            $cliente = Client::create(array_merge($data, ['store_id' => current_store_id()]));
        } catch (QueryException $e) {
            if ($response = $this->clientContactDuplicateResponse($e)) {
                return $response;
            }

            throw $e;
        }

        return redirect()->route('clientes.index')
            ->with('success', 'Cliente criado com sucesso.');
    }

    /**
     * Display the specified client.
     */
    public function show(Request $request, Client $cliente)
    {
        $cliente->load(['notes.user', 'tags']);

        $activities = ActivityLogQuery::forSubject($cliente);

        $marcacoesDefaultDesde = now()->startOfDay()->subMonths(6)->toDateString();
        $marcacoesDefaultAte = now()->startOfDay()->addMonths(6)->toDateString();
        $vendasDefaultDesde = now()->copy()->startOfMonth()->toDateString();
        $vendasDefaultAte = now()->copy()->endOfMonth()->toDateString();

        $validTabs = ['tab-details', 'tab-marcacoes', 'tab-vendas', 'tab-estatisticas', 'tab-log', 'tab-notas'];
        $activeTab = $request->get('active_tab', 'tab-details');
        if (! in_array($activeTab, $validTabs, true)) {
            $activeTab = 'tab-details';
        }

        // Filtros Marcações (independentes dos de vendas)
        $marcacoesDesde = $request->get('marcacoes_desde');
        $marcacoesAte = $request->get('marcacoes_ate');
        $marcacoesServico = $request->get('marcacoes_servico');
        $marcacoesTecnico = $request->get('marcacoes_tecnico');
        $marcacoesEstado = $request->get('marcacoes_estado');

        // Filtros Vendas
        $vendasDesde = $request->get('vendas_desde');
        $vendasAte = $request->get('vendas_ate');
        $vendasServico = $request->get('vendas_servico');
        $vendasTecnico = $request->get('vendas_tecnico');
        $vendasEstado = $request->get('vendas_estado');

        // Valores por defeito: marcações ±6 meses; vendas mês corrente (1.º → último dia)
        if (! $marcacoesDesde) {
            $marcacoesDesde = $marcacoesDefaultDesde;
        }
        if (! $marcacoesAte) {
            $marcacoesAte = $marcacoesDefaultAte;
        }
        if (! $vendasDesde) {
            $vendasDesde = $vendasDefaultDesde;
        }
        if (! $vendasAte) {
            $vendasAte = $vendasDefaultAte;
        }

        // Base query marcações
        $marcacoesQuery = $cliente->calendarEvents()
            ->where('event_type', CalendarEvent::TYPE_MARCACAO);

        if ($marcacoesDesde) {
            $marcacoesQuery->whereDate('start_at', '>=', $marcacoesDesde);
        }
        if ($marcacoesAte) {
            $marcacoesQuery->whereDate('start_at', '<=', $marcacoesAte);
        }

        if ($marcacoesServico) {
            $marcacoesQuery->whereHas('eventServiceItems', fn ($q) => $q->where('service_id', $marcacoesServico));
        }
        if ($marcacoesTecnico) {
            $marcacoesQuery->where('user_id', $marcacoesTecnico);
        }
        if ($marcacoesEstado) {
            $marcacoesQuery->where('status', $marcacoesEstado);
        }

        $marcacoes = $marcacoesQuery
            ->with(['client', 'user', 'eventServiceItems.service.category', 'eventServiceItems.extras.extra'])
            ->orderByDesc('start_at')
            ->limit(200)
            ->get();

        $marcacoesTotais = \App\Support\MarcacoesReportEstadoFilter::totaisFromEvents($marcacoes);

        // Vendas (mesma lógica do relatório de vendas, filtrado por cliente)
        $sales = $this->vendasReportService->reportQuery([
            'desde' => $vendasDesde,
            'ate' => $vendasAte,
            'cliente' => $cliente->id,
            'servico' => $vendasServico,
            'tecnico' => $vendasTecnico,
            'estado' => $vendasEstado,
        ])
            ->with(['client', 'calendarEvent.user', 'calendarEvent.eventServiceItems.extras.extra', 'items.service', 'items.extra', 'items.calendarEventService.service', 'items.calendarEventService.event.user'])
            ->orderByDesc('data_emissao')
            ->orderByDesc('id')
            ->get();

        $allVendasLines = $this->vendasReportService->resumoCollection($sales, $vendasServico, $vendasTecnico);
        $vendasTotais = $this->vendasReportService->totaisRodape($allVendasLines);

        $vendasPage = max(1, (int) $request->get('vendas_page', 1));
        $vendasPerPage = 100;
        $vendasSlice = $allVendasLines->slice(($vendasPage - 1) * $vendasPerPage, $vendasPerPage)->values();
        $vendas = new LengthAwarePaginator(
            $vendasSlice,
            $allVendasLines->count(),
            $vendasPerPage,
            $vendasPage,
            [
                'path' => $request->url(),
                'pageName' => 'vendas_page',
            ]
        );
        $vendas->withQueryString();

        // Opções para dropdowns (serviços e técnicos presentes nos dados do cliente)
        $servicosCliente = \App\Models\Service::query()
            ->forStore(current_store_id())
            ->join('calendar_event_services', 'services.id', '=', 'calendar_event_services.service_id')
            ->join('calendar_events', 'calendar_events.id', '=', 'calendar_event_services.calendar_event_id')
            ->where('calendar_events.client_id', $cliente->id)
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->select('services.id', 'services.name')
            ->distinct()
            ->orderBy('services.name')
            ->get();

        $tecnicosCliente = \App\Models\User::query()
            ->join('calendar_events', 'calendar_events.user_id', '=', 'users.id')
            ->where('calendar_events.store_id', current_store_id())
            ->where('calendar_events.client_id', $cliente->id)
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->select('users.id', 'users.name')
            ->distinct()
            ->orderBy('users.name')
            ->get();

        $stats = $this->buildClientStats($cliente);

        // KPIs de receita: considerar apenas marcações com vendas concluídas (pagas)
        $totalGasto = Sale::query()
            ->where('store_id', current_store_id())
            ->where('client_id', $cliente->id)
            ->where('status', Sale::STATUS_PAGO)
            ->whereHas('calendarEvent', function ($q) {
                $q->where('store_id', current_store_id())
                    ->where('event_type', CalendarEvent::TYPE_MARCACAO)
                    ->where('status', '!=', CalendarEvent::STATUS_CANCELADO);
            })
            ->sum('total');

        $totalMarcacoesComVenda = Sale::query()
            ->where('store_id', current_store_id())
            ->where('client_id', $cliente->id)
            ->where('status', Sale::STATUS_PAGO)
            ->whereHas('calendarEvent', function ($q) {
                $q->where('store_id', current_store_id())
                    ->where('event_type', CalendarEvent::TYPE_MARCACAO)
                    ->where('status', '!=', CalendarEvent::STATUS_CANCELADO);
            })
            ->distinct('calendar_event_id')
            ->count('calendar_event_id');

        $ticketMedio = $totalMarcacoesComVenda > 0
            ? (float) $totalGasto / $totalMarcacoesComVenda
            : null;

        $agents = Agent::forStore(current_store_id())->where('status', Agent::STATUS_ACTIVE)
            ->whereHas('user', fn ($q) => $q->where('role', '!=', User::ROLE_ADMIN))
            ->with('user')
            ->orderBy('name')
            ->get();

        return view('clientes.show', compact(
            'cliente',
            'activities',
            'marcacoes',
            'marcacoesTotais',
            'vendas',
            'vendasTotais',
            'stats',
            'totalGasto',
            'ticketMedio',
            'agents',
            'activeTab',
            'marcacoesDesde',
            'marcacoesAte',
            'marcacoesServico',
            'marcacoesTecnico',
            'marcacoesEstado',
            'vendasDesde',
            'vendasAte',
            'vendasServico',
            'vendasTecnico',
            'vendasEstado',
            'servicosCliente',
            'tecnicosCliente'
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
        $currentClientEmail = strtolower(trim((string) ($cliente->email ?? '')));
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', new ClientFullName],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('clients', 'email')->ignore($cliente->id)->where(fn ($q) => $q->where('store_id', current_store_id()))],
            'phone' => ['required', 'string', 'max:50', new UniqueClientPhone($cliente->id)],
            'nif' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(array_keys(Client::genders()))],
            'nationality' => ['nullable', 'string', 'max:100'],
            'marital_status' => ['nullable', Rule::in(array_keys(Client::maritalStatuses()))],
            'origem' => ['nullable', 'string', 'max:255'],
            'profissao' => ['nullable', 'string', 'max:255'],
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
        ], $this->clientContactValidationMessages());

        if ($request->hasFile('avatar')) {
            if ($cliente->avatar && Storage::disk('public')->exists($cliente->avatar)) {
                Storage::disk('public')->delete($cliente->avatar);
            }
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        } else {
            unset($validated['avatar']);
        }

        $newClientEmail = strtolower(trim((string) ($validated['email'] ?? '')));
        $clientEmailChanged = $newClientEmail !== $currentClientEmail;
        $linkedBookingUser = User::query()
            ->where('client_id', $cliente->id)
            ->where('role', User::ROLE_CLIENTE)
            ->first();

        if ($clientEmailChanged && $linkedBookingUser instanceof User) {
            if ($newClientEmail === '') {
                return back()
                    ->withErrors(['email' => 'Este cliente está ligado a login online. O email não pode ficar vazio.'])
                    ->withInput();
            }

            $emailConflict = User::query()
                ->whereRaw('LOWER(email) = ?', [$newClientEmail])
                ->where('id', '!=', $linkedBookingUser->id)
                ->exists();

            if ($emailConflict) {
                return back()
                    ->withErrors(['email' => 'Este email já está em uso noutro utilizador.'])
                    ->withInput();
            }
        }

        try {
            $cliente->update($validated);
        } catch (QueryException $e) {
            if ($response = $this->clientContactDuplicateResponse($e)) {
                return $response;
            }

            throw $e;
        }

        if ($clientEmailChanged && $linkedBookingUser instanceof User) {
            $linkedBookingUser->forceFill([
                'email' => $newClientEmail,
                'email_verified_at' => now(),
            ])->save();
        }

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
            ->where('store_id', current_store_id())
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO);

        $totalMarcacoes = $marcacoesBase->count();
        $clienteRecorrente = $totalMarcacoes >= 2;

        $receitaPorMes = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $start = $date->copy()->startOfMonth();
            $end = $date->copy()->endOfMonth();

            // Receita mensal: apenas marcações com vendas concluídas (pagas)
            $receita = Sale::query()
                ->where('store_id', current_store_id())
                ->where('client_id', $cliente->id)
                ->where('status', Sale::STATUS_PAGO)
                ->whereHas('calendarEvent', function ($q) use ($start, $end) {
                    $q->where('store_id', current_store_id())
                        ->where('event_type', CalendarEvent::TYPE_MARCACAO)
                        ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
                        ->whereBetween('start_at', [$start, $end]);
                })
                ->sum('total');

            $receitaPorMes[] = [
                'month' => $date->locale('pt_PT')->translatedFormat('M Y'),
                'revenue' => round((float) $receita, 2),
            ];
        }

        $topServicos = CalendarEventService::query()
            ->join('calendar_events', 'calendar_event_services.calendar_event_id', '=', 'calendar_events.id')
            ->join('services', 'calendar_event_services.service_id', '=', 'services.id')
            ->where('calendar_events.store_id', current_store_id())
            ->where('calendar_events.client_id', $cliente->id)
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('calendar_events.status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->selectRaw('services.name as service_name, count(*) as total')
            ->groupBy('services.id', 'services.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $tecnicoPreferidoRow = CalendarEvent::forStore(current_store_id())->where('client_id', $cliente->id)
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

    /**
     * @return array<string, string>
     */
    private function clientContactValidationMessages(): array
    {
        return [
            'email.unique' => 'Este email já está registado noutro cliente.',
        ];
    }

    private function clientContactDuplicateResponse(QueryException $e): ?\Illuminate\Http\RedirectResponse
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (int) ($e->errorInfo[1] ?? 0);
        if ($sqlState !== '23000' || $driverCode !== 1062) {
            return null;
        }

        $message = $e->getMessage();
        if (str_contains($message, 'clients_store_phone_unique')) {
            return back()
                ->withErrors(['phone' => 'Este telemóvel já está registado noutro cliente.'])
                ->withInput();
        }
        if (str_contains($message, 'clients_store_email_unique')) {
            return back()
                ->withErrors(['email' => 'Este email já está registado noutro cliente.'])
                ->withInput();
        }

        return back()
            ->withErrors(['phone' => 'Este contacto já está registado noutro cliente.'])
            ->withInput();
    }
}
